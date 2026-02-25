<?php

declare(strict_types=1);

namespace Wali\Web;

use PDO;

/**
 * Base Model
 *
 * Menyediakan koneksi database dan query builder dasar.
 * Model turunan hanya perlu menentukan properti $table.
 *
 * Contoh:
 * class UserModel extends Model {
 *     protected string $table = 'users';
 * }
 */
abstract class Model
{
    /**
     * Instance koneksi database PDO
     */
    protected PDO $db;

    /**
     * Nama tabel database
     */
    protected string $table;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Membuat instance query builder untuk tabel model.
     */
    protected function builder(): Builder
    {
        return new Builder($this->db, $this->table);
    }

    /**
     * Ambil semua data.
     */
    public function findAll(): array
    {
        return $this->builder()->get();
    }

    /**
     * Ambil satu data berdasarkan ID.
     */
    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        return $this->builder()
            ->where($primaryKey, $id)
            ->first();
    }

    /**
     * Insert data baru.
     */
    public function insert(array $data): bool
    {
        return $this->builder()->insert($data);
    }

    /**
     * Update data berdasarkan ID.
     */
    public function update(int|string $id, array $data, string $primaryKey = 'id'): bool
    {
        return $this->builder()
            ->where($primaryKey, $id)
            ->update($data);
    }

    /**
     * Hapus data berdasarkan ID.
     */
    public function delete(int|string $id, string $primaryKey = 'id'): bool
    {
        return $this->builder()
            ->where($primaryKey, $id)
            ->delete();
    }
}
