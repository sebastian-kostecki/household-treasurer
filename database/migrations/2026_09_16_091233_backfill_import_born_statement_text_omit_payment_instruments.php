<?php

use App\Actions\BackfillImportBornStatementTextOmit;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(BackfillImportBornStatementTextOmit::class)->handle();
    }

    public function down(): void
    {
        //
    }
};
