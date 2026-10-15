<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Satu baris per email (primary key = email). Menyimpan OTP (dalam bentuk HMAC, bukan teks asli)
 * dan, setelah OTP benar, hash token registrasi yang dipakai di POST /auth/register.
 */
class CreateEmailOtpsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'email'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'otp_hash'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'attempts'         => ['type' => 'SMALLINT', 'default' => 0],
            'last_sent_at'     => ['type' => 'DATETIME'],
            'expires_at'       => ['type' => 'DATETIME'],
            'verified_at'      => ['type' => 'DATETIME', 'null' => true],
            'token_hash'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'token_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('email', true);
        $this->forge->createTable('email_otps');
    }

    public function down()
    {
        $this->forge->dropTable('email_otps', true);
    }
}