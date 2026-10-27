<?php
namespace Lms\Core;

use Lms\Core\Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $useUuid = false;
    protected static bool $useTimestamps = true;
    protected static bool $useSoftDeletes = true;

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function __get(string $name): mixed
    {
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]) || isset($this->relations[$name]);
    }

    public function toArray(): array
    {
        $data = $this->attributes;
        foreach ($this->relations as $key => $value) {
            if ($value instanceof Model) {
                $data[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $data[$key] = array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $value);
            } else {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    // ─── Query Methods ──────────────────────────────────────────────────────

    public static function table(): string
    {
        $prefix = Database::prefix('lms');
        return $prefix . static::$table;
    }

    public static function db(): \PDO
    {
        return Database::connection('lms');
    }

    public static function find(mixed $id): ?static
    {
        $pk = static::$primaryKey;
        $sql = "SELECT * FROM " . static::table() . " WHERE {$pk} = ?";

        if (static::$useSoftDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? static::hydrate($row) : null;
    }

    public static function findBy(string $column, mixed $value): ?static
    {
        $sql = "SELECT * FROM " . static::table() . " WHERE {$column} = ?";

        if (static::$useSoftDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute([$value]);
        $row = $stmt->fetch();

        return $row ? static::hydrate($row) : null;
    }

    public static function where(string $column, string $operator = '=', mixed $value = null): QueryBuilder
    {
        $builder = new QueryBuilder(static::table(), static::class);
        $builder->where($column, $operator, $value);

        if (static::$useSoftDeletes) {
            $builder->whereNull('deleted_at');
        }

        return $builder;
    }

    public static function all(): array
    {
        $sql = "SELECT * FROM " . static::table();

        if (static::$useSoftDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }

        $stmt = static::db()->query($sql);
        return array_map(fn($row) => static::hydrate($row), $stmt->fetchAll());
    }

    public static function count(): int
    {
        $sql = "SELECT COUNT(*) FROM " . static::table();

        if (static::$useSoftDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }

        return (int) static::db()->query($sql)->fetchColumn();
    }

    public static function hydrate(array $data): static
    {
        $model = new static();
        $model->attributes = $data;
        $model->original = $data;
        $model->exists = true;
        return $model;
    }

    // ─── Persistence ────────────────────────────────────────────────────────

    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        }
        return $this->insert();
    }

    protected function insert(): bool
    {
        $db = static::db();
        $table = static::table();

        if (static::$useUuid && empty($this->attributes['uuid'])) {
            $this->attributes['uuid'] = lms_uuid();
        }

        if (static::$useTimestamps) {
            $this->attributes['created_at'] = date('Y-m-d H:i:s');
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($this->attributes);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute(array_values($this->attributes));

        if ($result) {
            $this->attributes[static::$primaryKey] = (int) $db->lastInsertId();
            $this->original = $this->attributes;
            $this->exists = true;
        }

        return $result;
    }

    protected function update(): bool
    {
        $db = static::db();
        $table = static::table();
        $pk = static::$primaryKey;

        if (static::$useTimestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $changes = [];
        $params = [];

        foreach ($this->attributes as $key => $value) {
            if ($key === $pk) continue;
            if (array_key_exists($key, $this->original) && $this->original[$key] === $value) continue;

            $changes[] = "{$key} = ?";
            $params[] = $value;
        }

        if (empty($changes)) {
            return true;
        }

        $params[] = $this->attributes[$pk];

        $sql = "UPDATE {$table} SET " . implode(', ', $changes) . " WHERE {$pk} = ?";
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $db = static::db();
        $table = static::table();
        $pk = static::$primaryKey;

        if (static::$useSoftDeletes) {
            $sql = "UPDATE {$table} SET deleted_at = ? WHERE {$pk} = ?";
            $stmt = $db->prepare($sql);
            return $stmt->execute([date('Y-m-d H:i:s'), $this->attributes[$pk]]);
        }

        $sql = "DELETE FROM {$table} WHERE {$pk} = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$this->attributes[$pk]]);
    }

    public function forceDelete(): bool
    {
        $db = static::db();
        $table = static::table();
        $pk = static::$primaryKey;

        $sql = "DELETE FROM {$table} WHERE {$pk} = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$this->attributes[$pk]]);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function isDirty(): bool
    {
        return $this->attributes !== $this->original;
    }

    public function getKey(): mixed
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
