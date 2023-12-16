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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->timestamps();
            $table->string('name', 100);
            $table->string('code', 100);
        });

        //Insert default expenses:
        DB::table('expenses')->insert([
            ["code" => "701", "name" => "Equipo General"],
            ["code" => "793", "name" => "Gastos Medicos"],
            ["code" => "704", "name" => "Comida - Superv/Extranjero"],
            ["code" => "707", "name" => "Hotel"],
            ["code" => "714", "name" => "Miscelaneos"],
            ["code" => "716", "name" => "Combustible - Vehiculo"],
            ["code" => "717", "name" => "Reparacion - Vehiculo"],
            ["code" => "722", "name" => "Alojamiento - Supervisores"],
            ["code" => "731", "name" => "Articulos De Oficina"],
            ["code" => "732", "name" => "Inmigracion"],
            ["code" => "733", "name" => "Agua Para Beber"],
            ["code" => "737", "name" => "Telefono"],
            ["code" => "744", "name" => "Servicios (Agua, Luz, Etc)"],
            ["code" => "724", "name" => "Reparacion - Equipo"],
            ["code" => "740", "name" => "Ayuda Escolar"],
            ["code" => "776", "name" => "Transporte - Materiales"],
            ["code" => "777", "name" => "Transporte - Trabajadores"],
            ["code" => "790", "name" => "Comisiones Bancarias"],
            ["code" => "791", "name" => "Gastos De Envio (Paquet/Correo)"],
            ["code" => "1003", "name" => "Comida - Trabajadores Locales"],
            ["code" => "1004", "name" => "Comida - Superv/Extranjero"],
            ["code" => "1005", "name" => "Viaje - Encargado De País"],
            ["code" => "1006", "name" => "Renta De Vehiculos"],
            ["code" => "1007", "name" => "Hotel"],
            ["code" => "1011", "name" => "M/O Supervisor"],
            ["code" => "1012", "name" => "M/O Oficina"],
            ["code" => "1013", "name" => "Planos"],
            ["code" => "1014", "name" => "Miscelaneos"],
            ["code" => "1016", "name" => "Combustible - Vehiculo"],
            ["code" => "1017", "name" => "Reparacion - Vehiculo"],
            ["code" => "1018", "name" => "Seguro - Vehiculo"],
            ["code" => "1022", "name" => "Alojamiento - Supervisores"],
            ["code" => "1022.01", "name" => "Mantenimiento - Taller"],
            ["code" => "1023", "name" => "Combustible - Equipo"],
            ["code" => "1024", "name" => "Reparacion - Equipo"],
            ["code" => "1026", "name" => "Compra Terrenos"],
            ["code" => "1031", "name" => "Articulos De Oficina"],
            ["code" => "1032", "name" => "Inmigracion"],
            ["code" => "1033", "name" => "Agua Para Beber"],
            ["code" => "1036", "name" => "Fabricacion Andamios"],
            ["code" => "1037", "name" => "Telefono"],
            ["code" => "1044", "name" => "Servicios (Agua, Luz, Etc)"],
            ["code" => "1059", "name" => "Renta De Equipo"],
            ["code" => "1060", "name" => "Herramienta Pequeña"],
            ["code" => "1075", "name" => "Gastables - Equipo Y Herramienta"],
            ["code" => "1076", "name" => "Transporte - Materiales"],
            ["code" => "1077", "name" => "Transporte - Trabajadores"],
            ["code" => "1081", "name" => "M/O - General"],
            ["code" => "1090", "name" => "Comisiones Bancarias"],
            ["code" => "1091", "name" => "Gastos De Envio (Paqueteria/Correo)"],
            ["code" => "1093", "name" => "Gastos Medicos"],
            ["code" => "1101", "name" => "Equipo General"],
            ["code" => "1103", "name" => "Equipo: Pizarrones, Escritorios, Etc"],
            ["code" => "1602", "name" => "Materiales - Electricidad"],
            ["code" => "3101", "name" => "M/O - Cimentacion"],
            ["code" => "3120", "name" => "Materiales - Cimentacion"],
            ["code" => "3220", "name" => "Materiales - Pisos"],
            ["code" => "4221", "name" => "M/O - Levantado"],
            ["code" => "4222", "name" => "Materiales - Levantado"],
            ["code" => "5002", "name" => "M/O - Levantado Estructura"],
            ["code" => "5020", "name" => "Materiales - Levantado Estruc."],
            ["code" => "5102", "name" => "M/O - Fabricacion Estructura"],
            ["code" => "5110", "name" => "Materiales - Fabricacion Estructura"],
            ["code" => "9932", "name" => "Materiales - Pintura"],
            ["code" => "1009", "name" => "Mano De Obra - Seguridad"],
            ["code" => "1015", "name" => "Foto"],
            ["code" => "1020", "name" => "Costos - Ingenieros/Arquitectos"],
            ["code" => "1030", "name" => "Renta - Oficina/Taller"],
            ["code" => "1035", "name" => "Costos De Importacion"],
            ["code" => "1078", "name" => "Limpieza Final"],
            ["code" => "1080", "name" => "Bonos Y Liquidaciones"],
            ["code" => "1084", "name" => "Vehiculos De Perforacion"],
            ["code" => "1094", "name" => "Seguro - Empleado"],
            ["code" => "1097", "name" => "Seguro - General"],
            ["code" => "1098", "name" => "Impuestos De Salarios"],
            ["code" => "1541", "name" => "M/O - Plomeria"],
            ["code" => "1542", "name" => "Materiales - Plomeria"],
            ["code" => "1601", "name" => "M/O - Electricidad"],
            ["code" => "1604", "name" => "Electricidad Sub-Terranea"],
            ["code" => "1605", "name" => "Electricidad - Campus"],
            ["code" => "2701", "name" => "M/O Paisajismo"],
            ["code" => "2701.01", "name" => "Materiales Paisajismo"],
            ["code" => "3201", "name" => "M/O - Pisos"],
            ["code" => "3301", "name" => "M/O - Aceras"],
            ["code" => "3320", "name" => "Materiales - Aceras"],
            ["code" => "4002", "name" => "M/O - Albañileria General"],
            ["code" => "4020", "name" => "Materiales - Albañileria General"],
            ["code" => "4223", "name" => "M/O - Fabricacion De Block"],
            ["code" => "4224", "name" => "Materiales - Fabricacion De Block"],
            ["code" => "7020", "name" => "Materiales - Techo"],
            ["code" => "8002", "name" => "M/O - Fabricacion Puertas/Ventanas"],
            ["code" => "8020", "name" => "Materiales - Fab. Puertas/Ventanas"],
            ["code" => "8190", "name" => "Puertas Compradas"],
            ["code" => "8590", "name" => "Ventanas Compradas"],
            ["code" => "9931", "name" => "M/O - Pintura"],
            ["code" => "9941", "name" => "M/O - Repello"],
            ["code" => "9942", "name" => "Materiales - Repello"]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
