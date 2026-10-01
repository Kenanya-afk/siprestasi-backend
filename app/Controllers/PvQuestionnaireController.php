<?php

namespace App\Controllers;

use App\Models\PvQuestionModel;
use App\Models\PvQuestionnaireModel;

class PvQuestionnaireController extends BaseController
{
    /**
     * POST /pv-questionnaire
     * Body (JSON): { "year": "2026/2027", "head": "...", "subhead": "..." }
     * Route dipasang dengan filter auth:admin,superadmin
     */
    public function create()
    {
        $input = $this->request->getJSON(true) ?? [];

        $rules = ['year' => 'required', 'head' => 'required'];
        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $model = new PvQuestionnaireModel();

        // Nonaktifkan template lama biar cuma 1 yang aktif
        $model->where('is_active', true)->set(['is_active' => false])->update();

        $id = $model->insert([
            'year'      => $input['year'],
            'head'      => $input['head'],
            'subhead'   => $input['subhead'] ?? null,
            'is_active' => true,
        ]);

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'data'    => ['quiz_id' => $id],
        ]);
    }

    /**
     * POST /pv-questionnaire/{quizId}/questions
     * Body (JSON): { "head": "...", "subhead": "...", "weight": 20 }
     * Route dipasang dengan filter auth:admin,superadmin
     */
    public function addQuestion($quizId)
    {
        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'head'    => 'required',
            'subhead' => 'required',
            'weight'  => 'required|is_natural_no_zero',
        ];
        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $id = (new PvQuestionModel())->insert([
            'quiz_id'     => $quizId,
            'head'        => $input['head'],
            'subhead'     => $input['subhead'],
            'answer_type' => 'skala_1_5',
            'weight'      => $input['weight'],
        ]);

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'data'    => ['quest_id' => $id],
        ]);
    }

    /**
     * GET /pv-questionnaire/active
     * Route dipasang dengan filter auth
     */
    public function active()
    {
        $questionnaire = (new PvQuestionnaireModel())->getActive();

        if (! $questionnaire) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Belum ada kuesioner Pradita Value aktif.',
            ]);
        }

        $questions = (new PvQuestionModel())->where('quiz_id', $questionnaire['quiz_id'])->findAll();

        return $this->response->setJSON([
            'success' => true,
            'data'    => ['questionnaire' => $questionnaire, 'questions' => $questions],
        ]);
    }
}