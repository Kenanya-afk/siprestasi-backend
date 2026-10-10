<?php

namespace App\Controllers;

use App\Models\DetailResponseModel;
use App\Models\PraditaValueModel;
use App\Models\PvQuestionModel;
use App\Models\PvResponseModel;
use App\Models\ScoreResultModel;
use App\Models\SubmissionModel;
use App\Models\UserModel;

class PvResponseController extends BaseController
{
    /**
     * GET /pv-respond/{token}
     * Publik, tanpa login — evaluator buka dari link email.
     */
    public function form($token)
    {
        $response = (new PvResponseModel())->findByToken($token);

        if (! $response) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Link tidak valid.',
            ]);
        }

        if ($response['status'] === 'submitted') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Form ini sudah pernah diisi.',
            ]);
        }

        $questions = (new PvQuestionModel())->where('quiz_id', $response['quiz_id'])->findAll();

        $submission = (new SubmissionModel())->find($response['sub_id']);
        $mahasiswa  = $submission ? (new UserModel())->find($submission['user_id']) : null;

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'role'      => $response['role'],
                'reviewer'  => $response['reviewer'],
                'mahasiswa' => $mahasiswa['name'] ?? null,
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * POST /pv-respond/{token}
     * Body (JSON): { "answers": [ { "quest_id": 1, "score": 5 }, ... ] }
     * Publik, tanpa login.
     */
    public function submit($token)
    {
        $responseModel = new PvResponseModel();
        $response      = $responseModel->findByToken($token);

        if (! $response) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Link tidak valid.',
            ]);
        }

        if ($response['status'] === 'submitted') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Form ini sudah pernah diisi.',
            ]);
        }

        $input      = $this->request->getJSON(true) ?? [];
        $answers    = $input['answers'] ?? [];
        $questions  = (new PvQuestionModel())->where('quiz_id', $response['quiz_id'])->findAll();
        $questIds   = array_map('intval', array_column($questions, 'quest_id'));
        $weightById = array_combine($questIds, array_map('intval', array_column($questions, 'weight')));

        $answeredIds = array_map(static fn ($a) => (int) ($a['quest_id'] ?? 0), $answers);
        sort($answeredIds);
        $expectedIds = $questIds;
        sort($expectedIds);

        if ($answeredIds !== $expectedIds) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Semua pertanyaan wajib dijawab tepat satu kali.',
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $locked = $db->query(
            "SELECT resp_id FROM pv_responses WHERE resp_id = ? AND status <> 'submitted' FOR UPDATE",
            [$response['resp_id']]
        )->getRowArray();

        if (! $locked) {
            $db->transRollback();
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Form ini sudah pernah diisi.',
            ]);
        }

        $detailModel  = new DetailResponseModel();
        $weightedSum  = 0;

        foreach ($answers as $a) {
            $questId = isset($a['quest_id']) ? (int) $a['quest_id'] : null;
            $score   = $a['score'] ?? null;

            if (! in_array($questId, $questIds, true) || ! is_numeric($score) || $score < 1 || $score > 5) {
                $db->transRollback();
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Jawaban tidak valid untuk salah satu pertanyaan (skala 1-5).',
                ]);
            }

            $detailModel->insert([
                'resp_id'  => $response['resp_id'],
                'quest_id' => $questId,
                'score'    => $score,
            ]);

            $weightedSum += $score * $weightById[$questId];
        }

        // Normalisasi ke skala 0-100: skor maksimal per soal = 5
        $totalScore = (int) round($weightedSum / 5);

        $responseModel->update($response['resp_id'], [
            'status'      => 'submitted',
            'total_score' => $totalScore,
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Gagal menyimpan jawaban.',
            ]);
        }

        // Cek apakah 5/5 evaluator sudah selesai, kalau iya langsung agregasi
        $pvScore = (new PraditaValueModel())->aggregatePvScore($response['sub_id']);
        if ($pvScore !== null) {
            (new ScoreResultModel())->applyPvResult($response['sub_id'], $pvScore);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Jawaban berhasil disimpan.',
        ]);
    }
}