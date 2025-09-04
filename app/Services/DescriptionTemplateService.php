<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Category;

class DescriptionTemplateService
{
    public function processStoreDescription(Store $store, array $variables = []): string
    {
        $template = $store->description;

        if (empty($template)) {
            return '';
        }

        return $this->replaceVariables($template, $variables);
    }

    public function processCategoryDescription(Category $category, array $variables = []): string
    {
        $template = $category->description;

        if (empty($template)) {
            return '';
        }

        return $this->replaceVariables($template, $variables);
    }

    public function updateStoreDescription(Store $store, array $variables): bool
    {
        $updatedDescription = $this->processStoreDescription($store, $variables);

        if (!empty($updatedDescription)) {
            $store->update(['description' => $updatedDescription]);
            return true;
        }

        return false;
    }

    public function updateCategoryDescription(Category $category, array $variables): bool
    {
        $updatedDescription = $this->processCategoryDescription($category, $variables);

        if (!empty($updatedDescription)) {
            $category->update(['description' => $updatedDescription]);
            return true;
        }

        return false;
    }

    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }

        return $template;
    }
}
