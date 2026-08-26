<?php

namespace App\Livewire;

use App\Models\ProductFavorite;
use Livewire\Attributes\On;
use Livewire\Component;

class FavoritesBadge extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('favorites-changed')]
    public function refreshCount(): void
    {
        $this->count = auth()->check()
            ? ProductFavorite::where('user_id', auth()->id())->count()
            : 0;
    }

    public function render()
    {
        return view('livewire.favorites-badge');
    }
}
