<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $this->record($category, 'Tambah Kategori', "Kategori {$category->category_name} berhasil ditambahkan.");
    }

    public function updated(Category $category): void
    {
        $changes = array_intersect_key($category->getChanges(), array_flip(['category_name', 'description']));
        if (! $changes) return;

        $fields = array_map(fn (string $field) => $field === 'category_name' ? 'nama' : 'deskripsi', array_keys($changes));
        $this->record($category, 'Edit Kategori', "Kategori {$category->category_name} diperbarui (".implode(', ', $fields).').');
    }

    public function deleted(Category $category): void
    {
        $this->record($category, 'Hapus Kategori', "Kategori {$category->category_name} berhasil dihapus.");
    }

    private function record(Category $category, string $action, string $description): void
    {
        $user = Auth::user();
        if (! $user?->business_id) return;

        ActivityLog::create([
            'business_id' => $user->business_id,
            'user_id' => $user->user_id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}