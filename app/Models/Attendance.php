<?php

namespace App\Models;

use App\Helpers\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Job;
use App\Models\Expense;
use Carbon\Carbon;

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
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }
    public function expense(){
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }
    public function dates(): array
    {
        $dates = [];
        $from_date = new \DateTime($this->from_date);
        $to_date = Carbon::createFromDate(new \DateTime($this->to_date))->addDays(1)->toDateTime();
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($from_date, $interval, $to_date);


        foreach ($period as $date) {
            $dates[] = $date;
        }

        if (count($dates) === 0){
            if (Carbon::createFromDate($from_date)->isSameDay(Carbon::createFromDate($to_date))){
                $dates[] = Carbon::createFromDate($from_date)->toDateTime();
            }
        }


        return $dates;
    }
    public function datesWorkers():array
    {
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

    public function dayWorkers()
    {
        return AttendanceDayWorker::where('attendance_id', $this->id)->get();
    }

    public function attachWorkerDni(string $workerDni)
    {
        $records = collect($this->dates())->map(function(\DateTime $date) use ($workerDni){
            return [
                'worker_dni' => $workerDni,
                'attendance_id' => $this->id,
                'date' => $date->format('c'),
                'status' => AttendanceStatus::Present,
            ];
        })->toArray();

        AttendanceDayWorker::insert($records);
    }


    public function removeWorkerDni(string $workerDni)
    {
        $this->dayWorkers()->where('worker_dni', $workerDni)->each(function(AttendanceDayWorker $dayWorker){
            $dayWorker->delete();
        });
    }

    public function updateFromToDatesInAttendanceDayWorker()
    {
        $instance = $this;
        $dates = $this->dates();
        $dayWorkers = $this->dayWorkers();

        $dayWorkersDNIs = $dayWorkers->map(function(AttendanceDayWorker $dayWorker){
            return $dayWorker->worker_dni;
        })->unique();

        $dayWorkers->each(function(AttendanceDayWorker $dayWorker) use ($dates){
            $date = new \DateTime($dayWorker->date);
            if (!in_array($date, $dates)){
                $dayWorker->delete();
            }
        });
        $dayWorkers = $this->dayWorkers();
        collect($dates)->each(function(\DateTime $date) use ($dayWorkers, $instance, $dayWorkersDNIs){
            $dateString = $date->format('c');
            $dayWorker = $dayWorkers->first(function(AttendanceDayWorker $dayWorker) use ($dateString){
                return $dayWorker->date === $dateString;
            });
            if ($dayWorker === null){
                $dayWorkersDNIs->each(function(string $workerDni) use ($date, $instance){
                    AttendanceDayWorker::create([
                        'worker_dni' => $workerDni,
                        'attendance_id' => $instance->id,
                        'date' => $date->format('c'),
                        'status' => AttendanceStatus::Present,
                    ]);
                });
            }
        });
    }

    public function delete(){
        $this->dayWorkers()->each(function(AttendanceDayWorker $dayWorker){
            $dayWorker->delete();
        });
        parent::delete();
    }
}
