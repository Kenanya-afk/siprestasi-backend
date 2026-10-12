<?php

namespace App\Controllers;

use App\Models\UserModel;

/**
 * Profil milik user yang sedang login. Semua route memakai filter `auth`
 * (bukan auth:mahasiswa) supaya berlaku untuk semua role.
 *
 * Yang bisa diubah user sendiri: contact, foto profil, password.
 * name, email, induk, year, role, dll tidak bisa diubah dari sini.
 */
class ProfileController extends BaseController
{
    private const PICTURE_DIR = WRITEPATH . 'uploads/profile';

    /** Field yang tidak boleh dikirim ke PUT /profile. */
    private const LOCKED_FIELDS = [
        'name', 
        'email', 
        'induk', 
        'year', 
        'role', 
        'is_kaprodi',
        'is_active',
        'status_verifikasi', 
        'password', 
        'krs_path', 
        'picture',
    ];

    /**
     * GET /profile
     */
    public function show()
    {
        $user = (new UserModel())
            ->select('user_id, name, email, induk, contact, picture, year, role, is_kaprodi, status_verifikasi, created_at')
            ->find($this->userId());

        if (! $user) {
            return $this->fail(404, 'User tidak ditemukan.');
        }

        // Nama file di server tidak perlu diketahui klien.
        $user['picture_url'] = $user['picture'] ? base_url('profile/picture') : null;
        unset($user['picture']);

        return $this->response->setJSON(['success' => true, 'data' => $user]);
    }

    /**
     * PUT /profile   (JSON)  Body: { "contact": "08123456789" }
     */
    public function update()
    {
        $input = $this->request->getJSON(true) ?? [];

        $locked = array_values(array_intersect(array_keys($input), self::LOCKED_FIELDS));
        if ($locked) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Field berikut tidak dapat diubah: ' . implode(', ', $locked) . '.',
            ]);
        }

        $rules = [
            'contact' => [
                'rules'  => 'required|min_length[9]|max_length[15]|regex_match[/^[0-9]+$/]',
                'errors' => ['regex_match' => 'Nomor telepon hanya boleh berisi angka.'],
            ],
        ];
        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        // skipValidation: aturan model mewajibkan name/password, padahal hanya contact yang diubah.
        (new UserModel())->skipValidation(true)->update($this->userId(), ['contact' => $input['contact']]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => ['contact' => $input['contact']],
        ]);
    }

    /**
     * PUT /profile/password   (JSON)
     * Body: { "current_password": "...", "new_password": "...", "confirm_password": "..." }
     */
    public function changePassword()
    {
        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'current_password' => 'required',
            'new_password'     => [
                'rules'  => 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
                'errors' => ['regex_match' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan karakter spesial.'],
            ],
            'confirm_password' => 'required|matches[new_password]',
        ];
        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $users = new UserModel();
        $user  = $users->find($this->userId());

        if (! $user || ! password_verify($input['current_password'], $user['password'])) {
            return $this->fail(422, 'Password saat ini salah.');
        }
        if (password_verify($input['new_password'], $user['password'])) {
            return $this->fail(422, 'Password baru tidak boleh sama dengan password saat ini.');
        }

        $users->skipValidation(true)->update($this->userId(), [
            'password' => password_hash($input['new_password'], PASSWORD_BCRYPT),
        ]);

        session()->regenerate(true);

        return $this->response->setJSON(['success' => true, 'message' => 'Password berhasil diganti.']);
    }

    /**
     * GET /profile/picture  -> menyajikan foto profil milik sendiri
     */
    public function picture()
    {
        $path = $this->picturePath();
        if (! $path) {
            return $this->fail(404, 'Foto profil belum ada.');
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody(file_get_contents($path));
    }

    /**
     * POST /profile/picture   (multipart/form-data, field: picture)
     * Memakai POST karena PHP tidak mengisi $_FILES untuk request PUT multipart.
     */
    public function uploadPicture()
    {
        $rules = [
            'picture' => 'uploaded[picture]|max_size[picture,2048]|is_image[picture]|mime_in[picture,image/jpeg,image/png]',
        ];
        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $file = $this->request->getFile('picture');
        $name = $file->getRandomName();

        if (! is_dir(self::PICTURE_DIR)) {
            mkdir(self::PICTURE_DIR, 0755, true);
        }
        $file->move(self::PICTURE_DIR, $name);

        $old = $this->picturePath(); // path foto lama, kalau ada
        (new UserModel())->skipValidation(true)->update($this->userId(), ['picture' => $name]);
        if ($old) {
            @unlink($old);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data'    => ['picture_url' => base_url('profile/picture')],
        ]);
    }

    /**
     * DELETE /profile/picture
     */
    public function deletePicture()
    {
        $old = $this->picturePath();
        if (! $old) {
            return $this->fail(404, 'Foto profil belum ada.');
        }

        (new UserModel())->skipValidation(true)->update($this->userId(), ['picture' => null]);
        @unlink($old);

        return $this->response->setJSON(['success' => true, 'message' => 'Foto profil dihapus.']);
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    /** Path file foto milik user yang login, atau null kalau tidak ada / file hilang. */
    private function picturePath(): ?string
    {
        $user = (new UserModel())->select('picture')->find($this->userId());
        if (! $user || ! $user['picture']) {
            return null;
        }

        $path = self::PICTURE_DIR . '/' . basename($user['picture']); // basename: cegah path traversal
        return is_file($path) ? $path : null;
    }

    private function fail(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }
}