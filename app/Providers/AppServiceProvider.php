<?php

namespace App\Providers;

use App\Models\Disk;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        Actions\Action::configureUsing(modifyUsing: function ($action) {
            return $action->slideOver();
        });

        Tables\Actions\Action::configureUsing(modifyUsing: function ($action) {
            return $action->iconButton()->slideOver();
        });

        Tables\Columns\Column::configureUsing(modifyUsing: function ($column): void {
            $column->translateLabel();
        });

        Tables\Filters\Filter::configureUsing(modifyUsing: function ($filter): void {
            $filter->translateLabel();
        });

        Forms\Components\Field::configureUsing(modifyUsing: function ($field): void {
            $field->translateLabel();
        });

        Infolists\Components\Entry::configureUsing(modifyUsing: function ($entry): void {
            $entry->translateLabel();
        });

        // Serve local disks via the storage route temporary URLs
        Disk::query()->local()->each(fn (Disk $disk) => $disk->registerStorage());
    }
}
