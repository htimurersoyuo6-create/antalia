<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $rows=DB::table('users')->select('id','phone')->get();$map=[];$seen=[];
        foreach($rows as $row){$digits=preg_replace('/\\D+/','',(string)$row->phone);$norm=null;if($digits!==''){if(str_starts_with($digits,'00'))$digits=substr($digits,2);if(strlen($digits)===11&&str_starts_with($digits,'0'))$digits='90'.substr($digits,1);elseif(strlen($digits)===10)$digits='90'.$digits;if(strlen($digits)>=8&&strlen($digits)<=15)$norm='+'.$digits;}if($norm){if(isset($seen[$norm]))throw new RuntimeException('Existing duplicate phone numbers must be resolved before adding unique phone login.');$seen[$norm]=true;$map[$row->id]=$norm;}}
        Schema::table('users',function(Blueprint $t){$t->string('email')->nullable()->change();$t->string('phone_normalized',16)->nullable()->unique();$t->char('identity_hash',64)->nullable()->unique();});
        foreach($map as $id=>$phone)DB::table('users')->where('id',$id)->update(['phone_normalized'=>$phone]);
    }
    public function down(): void {
        if(DB::table('users')->whereNull('email')->exists())throw new RuntimeException('Cannot roll back phone-only accounts while they exist; assign email addresses first.');
        Schema::table('users',function(Blueprint $t){$t->dropUnique(['phone_normalized']);$t->dropUnique(['identity_hash']);$t->dropColumn(['phone_normalized','identity_hash']);$t->string('email')->nullable(false)->change();});
    }
};
