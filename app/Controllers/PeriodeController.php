<?php

namespace App\Controllers;

use App\Models\PeriodeModel;
use App\Models\LogModel;

class PeriodeController extends BaseController
{
    /**
     * POST /periode
     * Body (JSON): { "tahun_ajaran": "2026/2027", "tanggal_mulai": "2026-09-01", "tanggal_selesai": "2026-10-31" }
     * Route dipasang dengan filter auth:admin,superadmin
     */
    public function create()
    {
        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'tahun_ajaran'    => 'required',
            'tanggal_mulai'   => 'required|valid_date',
            'tanggal_selesai' => 'required|valid_date',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        if (strtotime($input['tanggal_selesai']) <= strtotime($input['tanggal_mulai'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => ['tanggal_selesai' => 'Harus setelah tanggal_mulai.'],
            ]);
        }

        $id = (new PeriodeModel())->insert([
            'tahun_ajaran'    => $input['tahun_ajaran'],
            'tanggal_mulai'   => $input['tanggal_mulai'],
            'tanggal_selesai' => $input['tanggal_selesai'],
            'is_closed'       => false,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        (new LogModel())->recordSystemLog(
            (int) session()->get('user_id'),
            "Buat periode pendaftaran {$input['tahun_ajaran']} ({$input['tanggal_mulai']} s/d {$input['tanggal_selesai']})"
        );

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Periode pendaftaran dibuat.',
            'data'    => ['periode_id' => $id],
        ]);
    }

    /**
     * GET /periode/current
     * Route dipasang dengan filter auth (siapa saja yang login boleh lihat)
     */
    public function current()
    {
        $periode = (new PeriodeModel())->getCurrent();

        if (! $periode) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Belum ada periode pendaftaran.',
            ]);
        }

        return $this->response->setJSON(['success' => true, 'data' => $periode]);
    }
}