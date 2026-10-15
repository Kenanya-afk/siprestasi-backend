<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Libraries\OtpService;

class AuthController extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * POST /auth/register
     * Body: nama, email, password, confirm_password, nim, no_telp, krs (file)
     */
    public function register()
    {
        $rules = [
            'registration_token' => 'required',
            'email' => [
            'rules'  => 'required|valid_email|is_unique[users.email]|regex_match[/@student\./]',
                'errors' => [
                'regex_match' => 'Email harus menggunakan domain student.',
            ],
        ],
        'nama' => [
            'rules'  => 'required|min_length[3]|max_length[150]|regex_match[/^[A-Za-z\s]+$/]',
            'errors' => [
                'regex_match' => 'Nama hanya boleh berisi huruf dan spasi.',
            ],
        ],
        'nim' => [
            'rules'  => 'required|min_length[10]|is_unique[users.induk]|regex_match[/^[0-9]+$/]',
            'errors' => [
                'regex_match' => 'NIM hanya boleh berisi angka.',
            ],
        ],
        'no_telp' => [
            'rules'  => 'required|min_length[9]|max_length[15]|regex_match[/^[0-9]+$/]',
            'errors' => [
                'regex_match' => 'Nomor telepon hanya boleh berisi angka.',
            ],
        ],
        'password' => [
            'rules'  => 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
            'errors' => [
                'regex_match' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan karakter spesial.',
            ],
        ],
        'confirm_password' => 'required|matches[password]',
        'krs' => 'uploaded[krs]|max_size[krs,2048]|mime_in[krs,image/jpeg,image/png,application/pdf]',
    ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $otp   = new OtpService();
        $email = OtpService::normalizeEmail((string) $this->request->getPost('email'));

        if (! $otp->validateToken($email, (string) $this->request->getPost('registration_token'))) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Verifikasi email diperlukan atau sudah kedaluwarsa. Silakan verifikasi email lagi.',
            ]);
        }

        // Ambil file KRS yang di-upload
        $fileKrs = $this->request->getFile('krs');

        // Generate nama acak supaya aman & tidak bentrok antar user
        $namaFileBaru = $fileKrs->getRandomName();

        // Pindahkan file dari folder temporary ke folder permanen kita
        $fileKrs->move(WRITEPATH . 'uploads/krs', $namaFileBaru);

        // Path lengkap file yang baru saja dipindahkan, buat dikirim ke AI service
        $pathFileKrs = WRITEPATH . 'uploads/krs/' . $namaFileBaru;

        // Defaultnya pending -- kalau AI service gagal dihubungi, status ini yang dipakai
        $statusVerifikasi = 'pending';

        try {
            $client = \Config\Services::curlrequest();

            $response = $client->post(env('aiService.baseUrl') . '/verify-krs', [
                'headers' => [
                'x-internal-key' => env('aiService.internalKey'),
            ],
            'multipart' => [
            'file' => new \CURLFile(
            $pathFileKrs,
            mime_content_type($pathFileKrs),
            $namaFileBaru
        ),
                'nama' => $this->request->getPost('nama'),
                'nim'  => $this->request->getPost('nim'),
         ],
        'timeout' => 15,
]);

    $bodyAi = (string) $response->getBody();

    log_message('error', 'RESPON AI MENTAH: ' . $bodyAi);

    $hasilAi = json_decode($bodyAi, true);

    log_message('error', 'HASIL AI DECODE: ' . print_r($hasilAi, true));

    $hasilAi = json_decode($response->getBody(), true);

    if (!isset($hasilAi['is_valid'])) {
    if (file_exists($pathFileKrs)) {
        unlink($pathFileKrs);
    }

    return $this->response->setStatusCode(503)->setJSON([
        'success' => false,
        'message' => 'Hasil verifikasi KRS tidak valid. Silakan coba lagi.',
    ]);
}

if (!$hasilAi['is_valid']) {
    if (file_exists($pathFileKrs)) {
        unlink($pathFileKrs);
    }

    return $this->response->setStatusCode(422)->setJSON([
        'success' => false,
        'message' => 'Registrasi ditolak. KRS tidak sesuai dengan ketentuan Program Studi Sistem Informasi.',
    ]);
}

    } catch (\Exception $e) {

        if (file_exists($pathFileKrs)) {
            unlink($pathFileKrs);
        }

        log_message(
            'error',
            'Gagal menghubungi AI service: ' . $e->getMessage()
        );

        return $this->response->setStatusCode(503)->setJSON([
            'success' => false,
            'message' => 'Verifikasi KRS sedang tidak tersedia. Silakan coba lagi.',
        ]);
    }

    // Ambil 2 digit pertama NIM sebagai angkatan, gabung jadi tahun 4 digit (24 -> 2024)
    $nimUser = $this->request->getPost('nim');
    $angkatan = '20' . substr($nimUser, 0, 2);

        $data = [
            'name'              => $this->request->getPost('nama'),
            'email'             => $email,
            'password'          => password_hash(
                                    $this->request->getPost('password'),
                                    PASSWORD_BCRYPT
                                    ),
            'induk'             => $this->request->getPost('nim'),
            'contact'           => $this->request->getPost('no_telp'),
            'krs_path'          => $namaFileBaru,
            'status_verifikasi' => 'terverifikasi',
            'role'              => 'mahasiswa',
            'year'              => $angkatan,
];

        if (! $this->userModel->insert($data)) {
        return $this->response->setStatusCode(422)->setJSON([
            'success' => false,
            'errors'  => $this->userModel->errors(),
            ]);
        }

        $otp->consume($email); // token registrasi sekali pakai

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Registrasi berhasil, silakan login.',
        ]);
    }

    /**
     * POST /auth/login
     * Body: email, password
     */
    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $this->userModel->findByEmail($email);

        if (! $user || ! password_verify($password, $user['password'])) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Email atau password salah.',
            ]);
        }

        if (! $user['is_active']) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Akun tidak aktif, hubungi admin.',
            ]);
        }

        if ($user['role'] === 'mahasiswa' && $user['status_verifikasi'] !== 'terverifikasi') {
            $pesan = match ($user['status_verifikasi']) {
                'pending' => 'Akun kamu masih menunggu verifikasi KRS. Coba lagi beberapa saat.',
                'ditolak' => 'Verifikasi KRS ditolak. Pastikan KRS yang diupload sesuai dan hubungi admin.',
                default   => 'Akun belum terverifikasi.',
            };

            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => $pesan,
            ]);
        }

        // Simpan session, jangan simpan password
        session()->set([
            'user_id'    => $user['user_id'],
            'nama'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'isLoggedIn' => true,
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'nama' => $user['name'],
                'role' => $user['role'],
            ],
        ]);
    }

    /**
     * POST /auth/logout
     */
    public function logout()
    {
        session()->destroy();

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
}