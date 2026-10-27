<?php
namespace Lms\Core;

abstract class Repository
{
    protected \PDO $db;
    protected string $table;
    protected string $modelClass;

    public function __construct()
    {
        $this->db = Database::connection('lms');
        $this->table = $this->getTable();
        $this->modelClass = $this->getModelClass();
    }

    abstract protected function getTable(): string;
    abstract protected function getModelClass(): string;

    public function find(mixed $id): mixed
    {
        $modelClass = $this->modelClass;
        return $modelClass::find($id);
    }

    public function findBy(string $column, mixed $value): mixed
    {
        $modelClass = $this->modelClass;
        return $modelClass::findBy($column, $value);
    }

    public function findAll(array $conditions = [], array $orderBy = [], int $limit = null): array
    {
        $modelClass = $this->modelClass;
        $query = $modelClass::where('1', '=', '1');

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, '=', $value);
            }
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function paginate(array $conditions = [], int $page = 1, int $perPage = 20, array $orderBy = ['created_at' => 'DESC']): array
    {
        $modelClass = $this->modelClass;
        $query = $modelClass::where('1', '=', '1');

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, '=', $value);
            }
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->paginate($page, $perPage);
    }

    public function create(array $data): mixed
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass($data);
        $model->save();
        return $model;
    }

    public function update(mixed $id, array $data): mixed
    {
        $model = $this->find($id);
        if (!$model) {
            throw new \Lms\Core\Exceptions\NotFoundException("Record not found");
        }
        $model->fill($data);
        $model->save();
        return $model;
    }

    public function delete(mixed $id): bool
    {
        $model = $this->find($id);
        if (!$model) {
            throw new \Lms\Core\Exceptions\NotFoundException("Record not found");
        }
        return $model->delete();
    }

    public function count(array $conditions = []): int
    {
        $modelClass = $this->modelClass;
        $query = $modelClass::where('1', '=', '1');

        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        return $query->count();
    }
}
