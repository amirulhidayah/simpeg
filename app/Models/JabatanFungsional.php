<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JabatanFungsional extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'jabatan_fungsional';
}
