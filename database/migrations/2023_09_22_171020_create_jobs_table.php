<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->timestamps();
            $table->string('name', 100);
            $table->string('code', 100);
        });

        //Insert default jobs:
        DB::table('jobs')->insert([
            ["code" => "0000", "name" => "Administrador"],
            ["code" => "2000.01", "name" => "Taller Juliaca"],
            ["code" => "2000.02", "name" => "Taller Pucallpa"],
            ["code" => "2001", "name" => "Proyect Suport"],
            ["code" => "3031", "name" => "Tryon Church - Dec ’23"],
            ["code" => "3032", "name" => "CFP - Dec ’23"],
            ["code" => "3033", "name" => "Markham Team - Mar ’24"],
            ["code" => "3034", "name" => "Chehalis Academy - Mar ’24"],
            ["code" => "3035", "name" => "Ozark Academy - Mar ’24"],
            ["code" => "3036", "name" => "Palisades Academy - Mar ’24"],
            ["code" => "3037", "name" => "SFP - June ’24"],
            ["code" => "2501", "name" => "MLT-Colegio Adventista Titicaca - Primary"],
            ["code" => "2501.01", "name" => "MLT-Colegio Adventista Titicaca - Pre-School"],
            ["code" => "1100", "name" => "MLT-Cabana"],
            ["code" => "1101", "name" => "MLT-Morogachi"],
            ["code" => "1102", "name" => "MLT-Nueva Jerusalen"],
            ["code" => "1103", "name" => "MLT-Chilla"],
            ["code" => "1104", "name" => "MLT-Monte Moriah"],
            ["code" => "1105", "name" => "MLT-Maravillas"],
            ["code" => "1106", "name" => "MLT-Nueva Jerusalen (Parco)"],
            ["code" => "1121", "name" => "MOP-Villa Jesús : Pucallpa"],
            ["code" => "1121.01", "name" => "MOP-Villa de Jesus: Pucallpa - Aula"]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
