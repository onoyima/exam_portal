<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\SystemSetting;

class SystemSettingRepository extends Repository
{
    protected function getTable(): string { return 'system_settings'; }
    protected function getModelClass(): string { return SystemSetting::class; }

    public function getValue(string $key, mixed $default = null): mixed
    {
        return SystemSetting::getValue($key, $default);
    }

    public function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        SystemSetting::setValue($key, $value, $group, $type);
    }

    public function getGroup(string $group): array
    {
        return SystemSetting::getGroup($group);
    }
}
