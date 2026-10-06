<?php

namespace App\Controllers;

use App\Models\IpkModel;
use App\Models\MasterDataModel;
use App\Models\SkpiModel;
use App\Models\SubmissionModel;
use App\Models\UserModel;
use App\Models\PrestasiModel;
use App\Models\BahasaModel;
use App\Models\OrganisasiModel;
use App\Models\LogModel;
use App\Models\ScoreResultModel;
use App\Models\PeriodeModel;
use App\Models\PvQuestionnaireModel;
use App\Models\PvResponseModel;

class PengajuanController extends BaseController
{
    /**
     * POST /pengajuan
     * Buat submission baru (status draft) + tarik IPK & SKPI dari master_data.
     * Route dipasang dengan filter auth:mahasiswa
     */
    public function create()
    {
        $periodeModel = new PeriodeModel();
        $periodeModel->closeIfDeadlinePassed();
        $currentPeriode = $periodeModel->getCurrent();

        if ($currentPeriode && $currentPeriode['is_closed']) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Periode pendaftaran sudah ditutup.',
            ]);
        }

        $userId = session()->get('user_id');

        $user = (new UserModel())->find($userId);
        if (! $user) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ]);
        }

        $submissionModel = new SubmissionModel();
        if ($submissionModel->findActiveByUser($userId)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Kamu sudah memiliki pengajuan aktif.',
            ]);
        }

        $master = (new MasterDataModel())->findLatestByInduk($user['induk']);
        if (! $master) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Data IPK/SKPI kamu belum tersedia. Hubungi Kaprodi.',
            ]);
        }

        $ipkModel  = new IpkModel();
        $skpiModel = new SkpiModel();

        $ipkScore  = $ipkModel->convertIpkScore((float) $master['ipk']);
        $skpiScore = $skpiModel->convertSkpiScore((int) $master['skpi']);

        $db = \Config\Database::connect();
        $db->transStart();

        $subId = $submissionModel->insert([
            'user_id' => $userId,
            'status'  => 'draft',
        ]);

        $ipkModel->insert([
            'sub_id'    => $subId,
            'ipk_nilai' => $master['ipk'],
            'ipk_score' => $ipkScore,
        ]);

        $skpiModel->insert([
            'sub_id'     => $subId,
            'skpi_nilai' => $master['skpi'],
            'skpi_score' => $skpiScore,
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Gagal membuat pengajuan.',
            ]);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Pengajuan berhasil dibuat.',
            'data'    => [
                'sub_id' => $subId,
                'status' => 'draft',
                'ipk'    => ['nilai' => $master['ipk'], 'score' => $ipkScore],
                'skpi'   => ['nilai' => $master['skpi'], 'score' => $skpiScore],
            ],
        ]);
    }
        /**
     * GET /pengajuan/me
     * Route dipasang dengan filter auth:mahasiswa
     */
    public function me()
    {
        $userId = session()->get('user_id');

        $submission = (new SubmissionModel())->findActiveByUser($userId);
        if (! $submission) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Kamu belum memiliki pengajuan.',
            ]);
        }

        $subId = $submission['sub_id'];
        $db    = \Config\Database::connect();

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'sub_id'          => $subId,
                'status'          => $submission['status'],
                'peer_reviewer_1' => $submission['peer_reviewer_1'],
                'peer_reviewer_2' => $submission['peer_reviewer_2'],
                'peer_reviewer_3' => $submission['peer_reviewer_3'],
                'ipk'             => $db->table('ipk')->where('sub_id', $subId)->get()->getRowArray(),
                'skpi'            => $db->table('skpi')->where('sub_id', $subId)->get()->getRowArray(),
                'prestasi'        => $db->table('prestasi')->where('sub_id', $subId)->get()->getRowArray(),
                'organisasi'      => $db->table('organisasi')->where('sub_id', $subId)->get()->getRowArray(),
                'bahasa'          => $db->table('bahasa')->where('sub_id', $subId)->get()->getRowArray(),
            ],
        ]);   
    }

        /**
     * POST /pengajuan/prestasi
     * Body (form-data): pres_cat, pres_file (file)
     * Route dipasang dengan filter auth:mahasiswa
     */
    /** POST /pengajuan/prestasi (form-data: pres_cat, pres_file) */
    public function uploadPrestasi()
    {
        return $this->saveCategoryUpload(PrestasiModel::class, 'pres', 'prestasi', 'Prestasi');
    }

    /** POST /pengajuan/organisasi (form-data: org_cat, org_file) */
    public function uploadOrganisasi()
    {
        return $this->saveCategoryUpload(OrganisasiModel::class, 'org', 'organisasi', 'Organisasi');
    }

    /** POST /pengajuan/bahasa (form-data: bah_cat, bah_file) */
    public function uploadBahasa()
    {
        return $this->saveCategoryUpload(BahasaModel::class, 'bah', 'bahasa', 'Sertifikat bahasa');
    }

    private function saveCategoryUpload(string $modelClass, string $prefix, string $folder, string $label)
    {
        $userId     = session()->get('user_id');
        $submission = (new SubmissionModel())->findActiveByUser($userId);

        if (! $submission) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Kamu belum memiliki pengajuan.',
            ]);
        }

        $existingCheck = (new $modelClass())->where('sub_id', $submission['sub_id'])->first();
        $sedangRevisi  = $existingCheck && $existingCheck[$prefix . '_status'] === 'revisi';

        if ($submission['status'] !== 'draft' && ! $sedangRevisi) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Pengajuan sudah dikirim dan dokumen ini tidak dalam status revisi.',
            ]);
        }

        $catField  = $prefix . '_cat';
        $fileField = $prefix . '_file';

        $rules = [
            $catField  => 'required|in_list[' . implode(',', array_keys($modelClass::SCORES)) . ']',
            $fileField => "uploaded[{$fileField}]|max_size[{$fileField},2048]|mime_in[{$fileField},image/jpeg,image/png,application/pdf]",
        ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $model    = new $modelClass();
        $kategori = $this->request->getPost($catField);
        $score    = $modelClass::SCORES[$kategori];

        $file    = $this->request->getFile($fileField);
        $newName = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads/' . $folder, $newName);

        $data = [
            'sub_id'             => $submission['sub_id'],
            $catField            => $kategori,
            $prefix . '_score'   => 0, // default 0, nanti dihitung di ScoreResultModel
            $fileField           => $newName,
            $prefix . '_status'  => 'pending',
        ];

                $existing = $model->where('sub_id', $submission['sub_id'])->first();

        if ($existing) {
            @unlink(WRITEPATH . 'uploads/' . $folder . '/' . $existing[$fileField]);
            $model->update($existing[$prefix . '_id'], $data);
        } else {
            $model->insert($data);
        }

        $user = (new UserModel())->find($userId);
        $this->validateDocumentWithAi($modelClass, $prefix, $folder, $submission['sub_id'], $kategori, $newName, $user['name'] ?? '');

        $docTerbaru = $model->where('sub_id', $submission['sub_id'])->first();

        return $this->response->setStatusCode($existing ? 200 : 201)->setJSON([
            'success' => true,
            'message' => $label . ' berhasil disimpan dan divalidasi.',
            'data'    => [
                $catField           => $docTerbaru[$catField],
                $prefix . '_score'  => $docTerbaru[$prefix . '_score'],
                $prefix . '_status' => $docTerbaru[$prefix . '_status'],
            ],
        ]);
    }

        /**
     * PUT /pengajuan/peer-reviewers
     * Body (JSON): { "peer_reviewer_1": "...", "peer_reviewer_2": "..." }
     */
        /**
     * PUT /pengajuan/peer-reviewers
     * Body (JSON): { "peer_reviewer_1": "...", "peer_reviewer_2": "...", "peer_reviewer_3": "..." }
     */
    public function setPeerReviewers()
    {
        $userId     = session()->get('user_id');
        $submission = (new SubmissionModel())->findActiveByUser($userId);

        if (! $submission) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Kamu belum memiliki pengajuan.',
            ]);
        }

        if ($submission['status'] !== 'top3') {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Pengajuan belum masuk Top 3.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'peer_reviewer_1' => 'required|valid_email',
            'peer_reviewer_2' => 'required|valid_email|differs[peer_reviewer_1]',
            'peer_reviewer_3' => 'required|valid_email|differs[peer_reviewer_1]|differs[peer_reviewer_2]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $ownEmail = strtolower((string) session()->get('email'));
        foreach (['peer_reviewer_1', 'peer_reviewer_2', 'peer_reviewer_3'] as $field) {
            if (strtolower($input[$field]) === $ownEmail) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'errors'  => [$field => 'Tidak boleh memakai email sendiri.'],
                ]);
            }
        }

        (new SubmissionModel())->update($submission['sub_id'], [
            'peer_reviewer_1' => $input['peer_reviewer_1'],
            'peer_reviewer_2' => $input['peer_reviewer_2'],
            'peer_reviewer_3' => $input['peer_reviewer_3'],
        ]);
        
        $this->dispatchQuestionnairesIfReady($submission['sub_id']);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tiga rekan sejawat berhasil disimpan.',
        ]);
    }

    private function getValidationIndicator(int $subId): string
    {
        $db = \Config\Database::connect();

        $statuses = [];
        foreach (['prestasi' => 'pres_status', 'organisasi' => 'org_status', 'bahasa' => 'bah_status'] as $table => $col) {
            $row = $db->table($table)->select($col)->where('sub_id', $subId)->get()->getRowArray();
            if ($row) {
                $statuses[] = $row[$col];
            }
        }

        if (in_array('ditolak', $statuses, true)) {
            return 'red';
        }
        if (in_array('pending', $statuses, true) || in_array('revisi', $statuses, true)) {
            return 'yellow';
        }
        return 'green';
    }

        /**
     * PUT /pengajuan/{subId}/lecturer-reviewers
     * Body (JSON): { "lecturer_reviewer_1": 5, "lecturer_reviewer_2": 8 }
     * Route dipasang dengan filter auth:admin,superadmin
     * Dipanggil setelah mahasiswa ini ditetapkan Top 3.
     */
    public function setLecturerReviewers($subId)
    {
        $submissionModel = new SubmissionModel();
        $submission      = $submissionModel->find($subId);

        if (! $submission) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan.',
            ]);
        }

        if ($submission['status'] !== 'top3') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Pengajuan belum masuk Top 3.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'lecturer_reviewer_1' => 'required|is_natural_no_zero',
            'lecturer_reviewer_2' => 'required|is_natural_no_zero|differs[lecturer_reviewer_1]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $userModel = new UserModel();
        $kaprodi   = $userModel->find($input['lecturer_reviewer_1']);
        $dosen2    = $userModel->find($input['lecturer_reviewer_2']);

        if (! $kaprodi || $kaprodi['role'] !== 'dosen' || ! $kaprodi['is_kaprodi']) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => ['lecturer_reviewer_1' => 'User ini bukan Kaprodi.'],
            ]);
        }

        if (! $dosen2 || $dosen2['role'] !== 'dosen') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => ['lecturer_reviewer_2' => 'User ini bukan dosen.'],
            ]);
        }

        $submissionModel->update($subId, [
            'lecturer_reviewer_1' => $input['lecturer_reviewer_1'],
            'lecturer_reviewer_2' => $input['lecturer_reviewer_2'],
        ]);

        $this->dispatchQuestionnairesIfReady($subId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Dosen penilai berhasil ditetapkan.',
        ]);
    }
    /**
     * POST /pengajuan/submit
     */
    public function submit()
    {
        $userId          = session()->get('user_id');
        $submissionModel = new SubmissionModel();
        $submission      = $submissionModel->findActiveByUser($userId);

        if (! $submission) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Kamu belum memiliki pengajuan.',
            ]);
        }

        if ($submission['status'] !== 'draft') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Pengajuan sudah pernah dikirim.',
            ]);
        }

        $errors = [];
        if (! (new IpkModel())->where('sub_id', $submission['sub_id'])->first()
            || ! (new SkpiModel())->where('sub_id', $submission['sub_id'])->first()) {
            $errors[] = 'Data IPK/SKPI belum lengkap.';
        }

        if ($errors) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Pengajuan belum lengkap.',
                'errors'  => $errors,
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $submissionModel->update($submission['sub_id'], [
            'status'       => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        (new LogModel())->recordLog($submission['sub_id'], $userId, 'submitted');
        
        (new ScoreResultModel())->calculateAllResults($submission['sub_id']);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Gagal mengirim pengajuan.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Pengajuan berhasil dikirim.',
            'data'    => ['sub_id' => $submission['sub_id'], 'status' => 'submitted'],
        ]);
    }

     /**
     * GET /pengajuan/ranking?angkatan=2024
     * Route dipasang dengan filter auth:admin,superadmin
     */
    public function ranking()
    {
        (new PeriodeModel())->closeIfDeadlinePassed();
        
        $angkatan = $this->request->getGet('angkatan');

        if (! $angkatan) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Parameter angkatan wajib diisi.',
            ]);
        }

        $db = \Config\Database::connect();

                $rows = $db->table('score_result sr')
            ->select('sr.sub_id, sr.total_score, sr.status, u.name, u.induk, u.year')
            ->join('submission s', 's.sub_id = sr.sub_id')
            ->join('users u', 'u.user_id = s.user_id')
            ->where('u.year', $angkatan)
            ->orderBy('sr.total_score', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['indicator'] = $this->getValidationIndicator((int) $row['sub_id']);
        }
        unset($row); // biar aman, hapus referensi setelah loop

        return $this->response->setJSON([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    private function dispatchQuestionnairesIfReady(int $subId): void
    {
        $submissionModel = new SubmissionModel();
        $submission      = $submissionModel->find($subId);

        $reviewersLengkap = ! empty($submission['peer_reviewer_1'])
            && ! empty($submission['peer_reviewer_2'])
            && ! empty($submission['peer_reviewer_3'])
            && ! empty($submission['lecturer_reviewer_1'])
            && ! empty($submission['lecturer_reviewer_2']);

        if (! $reviewersLengkap || $submission['status'] !== 'top3') {
            return; // masih nunggu salah satu pihak (mahasiswa/admin) lengkapi datanya
        }

        $questionnaire = (new PvQuestionnaireModel())->getActive();
        if (! $questionnaire) {
            return; // TODO: idealnya kasih notifikasi ke admin, kuesioner belum disiapkan
        }

        $userModel      = new UserModel();
        $responseModel  = new PvResponseModel();
        $kaprodi        = $userModel->find($submission['lecturer_reviewer_1']);
        $dosenLain      = $userModel->find($submission['lecturer_reviewer_2']);

        $evaluators = [
            ['reviewer' => $submission['peer_reviewer_1'], 'role' => 'sejawat'],
            ['reviewer' => $submission['peer_reviewer_2'], 'role' => 'sejawat'],
            ['reviewer' => $submission['peer_reviewer_3'], 'role' => 'sejawat'],
            ['reviewer' => $kaprodi['email'] ?? '', 'role' => 'dosen'],
            ['reviewer' => $dosenLain['email'] ?? '', 'role' => 'dosen'],
        ];

        foreach ($evaluators as $e) {
            $responseModel->insert([
                'sub_id'      => $subId,
                'quiz_id'     => $questionnaire['quiz_id'],
                'reviewer'    => $e['reviewer'],
                'role'        => $e['role'],
                'status'      => 'pending',
                'token'       => bin2hex(random_bytes(32)),
            ]);
        }

        $submissionModel->update($subId, ['status' => 'pending_reviewed']);
    }

    private function validateDocumentWithAi(string $modelClass, string $prefix, string $folder, int $subId, string $kategori, string $fileName, string $namaMahasiswa): void
    {
        $filePath = WRITEPATH . 'uploads/' . $folder . '/' . $fileName;
        if (! is_file($filePath)) {
            return;
        }

        $verdict = 'ragu';
        try {
            $url = env('aiService.baseUrl') . '/verify-certificate';
            
            // Siapkan data multipart menggunakan CURLFile asli PHP
            $postData = [
                'file'     => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $fileName),
                'nama'     => $namaMahasiswa,
                'kategori' => $kategori,
                'tipe'     => $folder,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-internal-key: ' . env('aiService.internalKey')
            ]);

            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                log_message('error', 'Gagal menghubungi AI service (cURL error): ' . $curlError);
            } elseif ($httpCode !== 200) {
                log_message('error', 'AI Service Error Response (HTTP ' . $httpCode . '): ' . $responseBody);
            } else {
                $hasil   = json_decode($responseBody, true);
                $verdict = $hasil['verdict'] ?? 'ragu';
            }
        } catch (\Exception $e) {
            log_message('error', 'Gagal menghubungi AI service (Exception): ' . $e->getMessage());
        }

        $model = new $modelClass();
        $doc   = $model->where('sub_id', $subId)->first();
        if (! $doc) {
            return;
        }

        if ($verdict === 'sesuai') {
            $model->update(
                $doc[$prefix . '_id'], 
                [
                    $prefix . '_status' => 'verified',
                    $prefix . '_score'  => $modelClass::SCORES[$kategori] ?? 0,
                ]
            );
        } elseif ($verdict === 'tidak_sesuai') {
            $model->update($doc[$prefix . '_id'], [
                $prefix . '_status' => 'revisi',
                $prefix . '_score'  => 0,
            ]);
        }
        // 'ragu' -> tetap pending, skor tetap 0, nunggu admin

        (new ScoreResultModel())->calculateAllResults($subId);
    }

}