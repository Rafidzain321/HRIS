<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\SioSimOperator;

class SioSimSeeder extends Seeder {
    public function run(): void {
        SioSimOperator::truncate();
        $data = [
            ['no'=>1,'nama'=>'DISYA  AIDIL KOSI','license_expired'=>'2028-08-15','kp_expired'=>'2024-10-19','sio_expired'=>'2027-09-19'],
            ['no'=>2,'nama'=>'RATNO WIYONO','license_expired'=>'2028-05-04','kp_expired'=>'2024-08-16','sio_expired'=>'2027-07-28'],
            ['no'=>3,'nama'=>'M NUR','license_expired'=>'2029-03-15','kp_expired'=>'2024-09-26','sio_expired'=>'2027-07-28'],
            ['no'=>4,'nama'=>'AZLAN EFENDI','license_expired'=>'2026-05-05','kp_expired'=>'2024-08-31','sio_expired'=>'2027-09-19'],
            ['no'=>5,'nama'=>'AHMAD SYAH','license_expired'=>'2026-07-06','kp_expired'=>'2024-08-25','sio_expired'=>'2027-09-19'],
            ['no'=>6,'nama'=>'BUDI HANDOKO','license_expired'=>'2025-07-23','kp_expired'=>'2024-10-02','sio_expired'=>'2027-07-28'],
            ['no'=>7,'nama'=>'LACTOGEN NAINGGOLAN','license_expired'=>'2029-03-19','kp_expired'=>'2024-10-02','sio_expired'=>'2027-07-28'],
            ['no'=>8,'nama'=>'SATRIYON','license_expired'=>'2027-01-31','kp_expired'=>'2024-10-02','sio_expired'=>'2027-07-28'],
            ['no'=>9,'nama'=>'IDRIS','license_expired'=>'2026-07-15','kp_expired'=>'2024-10-02','sio_expired'=>'2027-07-28'],
            ['no'=>10,'nama'=>'DEDEK SUSANTO','license_expired'=>'2027-01-20','kp_expired'=>'2024-10-09','sio_expired'=>'2027-07-28'],
            ['no'=>11,'nama'=>'IRVAN','license_expired'=>'2026-02-05','kp_expired'=>'2024-10-10','sio_expired'=>'2027-07-28'],
            ['no'=>12,'nama'=>'MUSTAFA','license_expired'=>'2026-07-28','kp_expired'=>'2024-08-31','sio_expired'=>'2027-09-19'],
            ['no'=>13,'nama'=>'DEZITA CHANDRA','license_expired'=>'2028-01-10','kp_expired'=>'2024-10-17','sio_expired'=>'2027-09-19'],
            ['no'=>14,'nama'=>'HAVIZ AL RASYID','license_expired'=>'2026-06-23','kp_expired'=>'2024-10-02','sio_expired'=>'2027-09-19'],
            ['no'=>15,'nama'=>'HENDRA FERI','license_expired'=>'2027-09-20','kp_expired'=>'2024-10-09','sio_expired'=>'2027-09-19'],
            ['no'=>16,'nama'=>'OKI CHARLES','license_expired'=>'2028-06-13','kp_expired'=>'2024-08-31','sio_expired'=>null],
            ['no'=>17,'nama'=>'IRFAN DASMANTO','license_expired'=>'2025-11-24','kp_expired'=>'2024-10-02','sio_expired'=>null],
            ['no'=>18,'nama'=>'FERI SUSANTO','license_expired'=>'2025-06-25','kp_expired'=>'2024-10-02','sio_expired'=>null],
            ['no'=>19,'nama'=>'MUJIONO','license_expired'=>'2024-10-12','kp_expired'=>'2024-08-31','sio_expired'=>null],
            ['no'=>20,'nama'=>'JOSUA WIRONI HUTASOIT','license_expired'=>'2029-05-03','kp_expired'=>'2024-10-02','sio_expired'=>null],
            ['no'=>21,'nama'=>'HENDRI NANANG','license_expired'=>'2029-05-03','kp_expired'=>'2024-08-27','sio_expired'=>null],
            ['no'=>22,'nama'=>'M PRIYO SUDARMONO','license_expired'=>'2025-01-13','kp_expired'=>'2024-10-12','sio_expired'=>null],
            ['no'=>23,'nama'=>'HARIS HUTABARAT','license_expired'=>'2028-04-04','kp_expired'=>'2024-08-31','sio_expired'=>'2027-09-19'],
        ];
        SioSimOperator::insert($data);
        $this->command->info("✅ SIO & SIM: " . SioSimOperator::count() . " operators imported");
    }
}