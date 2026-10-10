<?php

namespace App\Controllers;

use App\Models\LogModel;

/**
 * Log hanya bisa dibaca. Tidak ada endpoint ubah/hapus log.
 */
class LogController extends BaseController
{
    /**
     * GET /superadmin/logs  (auth:superadmin) -> semua log, boleh filter user_id
     * Query: user_id, sub_id, action, from, to, page, per_page
     */
    public function all()
    {
        return $this->respondLogs($this->filters());
    }

    /**
     * GET /admin/logs  (auth:admin) -> hanya aksi milik admin yang sedang login.
     * user_id diambil dari session, BUKAN dari query, supaya admin tidak bisa
     * mengintip log admin lain dengan mengganti ?user_id=.
     */
    public function mine()
    {
        $filters            = $this->filters();
        $filters['user_id'] = (int) session()->get('user_id');

        return $this->respondLogs($filters);
    }

    private function filters(): array
    {
        return [
            'user_id' => $this->request->getGet('user_id'),
            'sub_id'  => $this->request->getGet('sub_id'),
            'action'  => $this->request->getGet('action'),
            'from'    => $this->validDate($this->request->getGet('from')),
            'to'      => $this->validDate($this->request->getGet('to')),
        ];
    }

    private function respondLogs(array $filters)
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        $result = (new LogModel())->search($filters, $page, $perPage);

        return $this->response->setJSON([
            'success' => true,
            'data'    => $result['rows'],
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    private function validDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }
}