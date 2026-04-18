<?php

namespace Tests\Unit;

use App\Traits\PreventModification;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PreventModificationTraitTest extends TestCase
{
    public function test_save_on_existing_row_throws(): void
    {
        $model = new class extends Model
        {
            use PreventModification;
        };
        $model->exists = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append-only');

        $model->save();
    }

    public function test_delete_throws(): void
    {
        $model = new class extends Model
        {
            use PreventModification;
        };
        $model->exists = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $model->delete();
    }
}
