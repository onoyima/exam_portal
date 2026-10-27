<?php
namespace Lms\Models;

use Lms\Core\Model;

class FeatureFlag extends Model
{
    protected static string $table = 'feature_flags';

    public static function isEnabled(string $feature): bool
    {
        $flag = static::findBy('feature_name', $feature);
        if (!$flag) return false;
        return (bool) $flag->is_enabled;
    }

    public static function enable(string $feature, ?array $rules = null): void
    {
        $flag = static::findBy('feature_name', $feature);
        if ($flag) {
            $flag->is_enabled = 1;
            if ($rules !== null) $flag->rules = json_encode($rules);
            $flag->save();
        } else {
            $flag = new static();
            $flag->feature_name = $feature;
            $flag->is_enabled = 1;
            $flag->rules = $rules ? json_encode($rules) : null;
            $flag->save();
        }
    }

    public static function disable(string $feature): void
    {
        $flag = static::findBy('feature_name', $feature);
        if ($flag) {
            $flag->is_enabled = 0;
            $flag->save();
        }
    }

    public function getRules(): ?array
    {
        $rules = $this->attributes['rules'] ?? null;
        if (is_string($rules)) {
            return json_decode($rules, true);
        }
        return $rules;
    }
}
