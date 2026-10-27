<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\FeatureFlag;

class FeatureFlagRepository extends Repository
{
    protected function getTable(): string { return 'feature_flags'; }
    protected function getModelClass(): string { return FeatureFlag::class; }

    public function isEnabled(string $feature): bool
    {
        return FeatureFlag::isEnabled($feature);
    }

    public function enable(string $feature, ?array $rules = null): void
    {
        FeatureFlag::enable($feature, $rules);
    }

    public function disable(string $feature): void
    {
        FeatureFlag::disable($feature);
    }

    public function getAllEnabled(): array
    {
        return FeatureFlag::where('is_enabled', '=', 1)
            ->orderBy('feature_name', 'ASC')
            ->get();
    }
}
