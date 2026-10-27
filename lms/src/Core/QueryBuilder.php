<?php
namespace Lms\Core;

class QueryBuilder
{
    private string $table;
    private string $modelClass;
    private array $wheres = [];
    private array $params = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private array $columns = ['*'];

    public function __construct(string $table, string $modelClass)
    {
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, string $operator = '=', mixed $value = null): self
    {
        if ($value === null && $operator !== '=') {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['AND', $column, $operator];
        $this->params[] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator = '=', mixed $value = null): self
    {
        if ($value === null && $operator !== '=') {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['OR', $column, $operator];
        $this->params[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['AND', "{$column} IN ({$placeholders})", ''];
        $this->params = array_merge($this->params, $values);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [$type, $table, "{$first} {$operator} {$second}"];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function whereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = ['AND', $sql, ''];
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['AND', "{$column} IS NULL", ''];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['AND', "{$column} IS NOT NULL", ''];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function take(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function skip(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function max(string $column): mixed
    {
        $sql = $this->buildSelect("MAX({$column})");
        $stmt = Model::db()->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $total = $this->count();
        $items = $this->get();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    public function count(): int
    {
        $sql = $this->buildSelect('COUNT(*)');
        $stmt = Model::db()->prepare($sql);
        $stmt->execute($this->params);
        return (int) $stmt->fetchColumn();
    }

    public function first(): mixed
    {
        $this->limit = 1;
        $items = $this->get();
        return $items[0] ?? null;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        $stmt = Model::db()->prepare($sql);
        $stmt->execute($this->params);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => call_user_func([$this->modelClass, 'hydrate'], $row), $rows);
    }

    public function pluck(string $column): array
    {
        $sql = $this->buildSelect($column);
        $stmt = Model::db()->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function update(array $values): int
    {
        $sets = [];
        $params = [];

        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "UPDATE " . $this->table . " SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        $stmt = Model::db()->prepare($sql);
        $stmt->execute(array_merge($params, $this->params));

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM " . $this->table;
        $sql .= $this->buildWhere();

        $stmt = Model::db()->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->rowCount();
    }

    private function buildSelect(string $columns = null): string
    {
        $cols = $columns ?? implode(', ', $this->columns);
        $sql = "SELECT {$cols} FROM " . $this->table;

        foreach ($this->joins as $join) {
            $sql .= " {$join[0]} JOIN {$join[1]} ON {$join[2]}";
        }

        $sql .= $this->buildWhere();

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            [$boolean, $column, $operator] = $where;

            if ($i === 0) {
                $parts[] = "{$column} {$operator} ?";
            } elseif (empty($operator)) {
                $parts[] = "{$boolean} {$column}";
            } else {
                $parts[] = "{$boolean} {$column} {$operator} ?";
            }
        }

        return " WHERE " . implode(' ', $parts);
    }
}
