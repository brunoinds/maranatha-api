<?php

namespace App\Models;

use App\Helpers\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Job;
use App\Models\Expense;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_date',
        'to_date',
        'job_code',
        'expense_code',
        'description',
        'user_id',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function job(){
        return Job::where('code', $this->job_code)->first();
    }
    public function expense(){
        return Expense::where('code', $this->expense_code)->first();
    }
    public function dates(): array{
        $dates = [];
        $from_date = new \DateTime($this->from_date);
        $to_date = new \DateTime($this->to_date);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($from_date, $interval, $to_date);


        

        foreach ($period as $date) {
            $dates[] = $date;
        }
        return $dates;
    }
    public function datesWorkers():array{
        $dayWorkers = $this->dayWorkers();
        $dates = $this->dates();

        $datesWorkers = [];
        foreach($dates as $date){
            $dateWorkers = [];
            foreach($dayWorkers as $dayWorker){
                if ((new \DateTime($dayWorker->date))->format('c') === $date->format('c')){
                    $dateWorkers[] = $dayWorker;
                }
            }
            $datesWorkers[] = [
                'date' => $date->format('c'),
                'workers' => $dateWorkers,
            ];
        }

    }

    public function dayWorkers(){
        return AttendanceDayWorker::where('attendance_id', $this->id)->get();
    }

    public function attachWorkerDni(string $workerDni){
        collect($this->dates())->each(function(\DateTime $date) use ($workerDni){
            AttendanceDayWorker::create([
                'worker_dni' => $workerDni,
                'attendance_id' => $this->id,
                'date' => $date->format('c'),
                'status' => AttendanceStatus::Present,
            ]);
        });
    }

    public function removeWorkerDni(string $workerDni){
        $this->dayWorkers()->where('worker_dni', $workerDni)->each(function(AttendanceDayWorker $dayWorker){
            $dayWorker->delete();
        });
    }

    public function delete(){
        $this->dayWorkers()->each(function(AttendanceDayWorker $dayWorker){
            $dayWorker->delete();
        });
        parent::delete();
    }
}
