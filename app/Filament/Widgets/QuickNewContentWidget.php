<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickNewContentWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-new-content';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';
}
