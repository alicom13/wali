<?php

declare(strict_types=1);

namespace Wali\Web;

use PDO;

class Builder
{
    protected PDO $db;
    protected string $table;

    protected array $select = ['*'];
    protected array $where = [];
    protected array $bindings = [];
    protected array $order = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(PDO $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Builder Methods
    |--------------------------------------------------------------------------
    */

    public function select(array $fields): self
    {
        $this->select = $fields;
        return $this;
    }

    public function where(string $field, mixed $value): self
    {
        $this->where[] = [$field, '=', $value, 'AND'];
        return $this;
    }

    public function orWhere(string $field, mixed $value): self
    {
        $this->where[] = [$field, '=', $value, 'OR'];
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        $direction = in_array($direction, ['ASC', 'DESC']) ? $direction : 'ASC';

        $this->order[] = "$field $direction";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Execution
    |--------------------------------------------------------------------------
    */

    public function get(): array
    {
        $stmt = $this->db->prepare($this->buildSelect());
        $stmt->execute($this->bindings);

        $result = $stmt->fetchAll();
        $this->reset();

        return $result;
    }

    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count(): int
    {
        $this->select(['COUNT(*) as total']);
        $result = $this->first();
        return (int)($result['total'] ?? 0);
    }

    public function insert(array $data): int
    {
        $fields = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ($fields) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return (int)$this->db->lastInsertId();
    }

    public function update(array $data): int
    {
        $set = implode(',', array_map(fn($f) => "$f = ?", array_keys($data)));

        $sql = "UPDATE {$this->table} SET $set";

        if ($this->where) {
            $sql .= " WHERE " . $this->compileWhere();
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ...array_values($data),
            ...$this->bindings
        ]);

        $affected = $stmt->rowCount();
        $this->reset();

        return $affected;
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if ($this->where) {
            $sql .= " WHERE " . $this->compileWhere();
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);

        $affected = $stmt->rowCount();
        $this->reset();

        return $affected;
    }

    /*
    |--------------------------------------------------------------------------
    | SQL Builder
    |--------------------------------------------------------------------------
    */

    protected function buildSelect(): string
    {
        $sql = "SELECT " . implode(',', $this->select)
             . " FROM {$this->table}";

        if ($this->where) {
            $sql .= " WHERE " . $this->compileWhere();
        }

        if ($this->order) {
            $sql .= " ORDER BY " . implode(',', $this->order);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    protected function compileWhere(): string
    {
        $conditions = [];

        foreach ($this->where as $index => [$field, $operator, $value, $boolean]) {

            $conditions[] = ($index === 0 ? '' : " $boolean ")
                . "$field $operator ?";

            $this->bindings[] = $value;
        }

        return implode('', $conditions);
    }

    protected function reset(): void
    {
        $this->select = ['*'];
        $this->where = [];
        $this->bindings = [];
        $this->order = [];
        $this->limit = null;
        $this->offset = null;
    }
}
