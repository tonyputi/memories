<?php

namespace App\Filament\Pages;

use App\Models\Medium;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class Wall extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static string $view = 'filament.pages.wall';

    public int $perPage = 30;

    public function loadMore(): void
    {
        $this->perPage += 30;
    }

    #[Computed]
    public function media(): Collection
    {
        return collect(Medium::query()
            ->with('disk')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage)
            ->items()
        )
            ->groupBy(function ($medium) {
                return $medium->created_at->format('Y-m-d');
            });
    }
}
