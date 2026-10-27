<?php
namespace Lms\Models;

use Lms\Core\Model;

class SystemSetting extends Model
{
    protected static string $table = 'system_settings';

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::findBy('setting_key', $key);
        if (!$setting) return $default;

        $value = $setting->attributes['setting_value'];
        $type = $setting->attributes['setting_type'];

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array($value, ['1', 'true', 'yes', 1, true], true),
            'json' => json_decode($value, true),
            'text' => (string) $value,
            default => $value,
        };
    }

    public static function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        $setting = static::findBy('setting_key', $key);
        $serialized = match ($type) {
            'json' => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        if ($setting) {
            $setting->setting_value = $serialized;
            $setting->save();
        } else {
            $setting = new static();
            $setting->setting_key = $key;
            $setting->setting_value = $serialized;
            $setting->setting_group = $group;
            $setting->setting_type = $type;
            $setting->save();
        }
    }

    public static function getGroup(string $group): array
    {
        $settings = static::where('setting_group', $group)->get();
        $result = [];
        foreach ($settings as $s) {
            $result[$s->setting_key] = static::getValue($s->setting_key);
        }
        return $result;
    }
}
