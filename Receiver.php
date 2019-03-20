<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Centers;
use Route;

class Receiver extends Model
{

	public $set_permissions = true;

	protected $table = 'receivers';

	protected $fillable = ['branch_id','name', 'email', 'mobile', 'address', 'area', 'city', 'image', 'image_token', 'ref_no', 'created_by'];

	protected $appends = ['image_name'];

	public static function routes(){
		Route::resource('receiver', 'ReceiverController',  ['except' => ['create']]);
		Route::post('/list-receiver',['as'=>'receiver.list','uses'=>'ReceiverController@listReceiver']);
		Route::post('/receiver-delete',['as'=>'receiver.delete','uses'=>'ReceiverController@destroy']);
		Route::get('/get-receivers',['as'=>'get.receivers','uses'=>'receiverController@getReceiver']);

		Route::get('/get-receivers-list',['as'=>'get.receivers.list','uses'=>'ReceiverController@getReceiverList']);
		Route::post('/post-receivers-list',['as'=>'post.receiver.list','uses'=>'ReceiverController@postReceiverList']);
		Route::post('/update-receivers-list',['as'=>'update.receiver.list','uses'=>'ReceiverController@updateReceiver']);
	}

	public function center(){
      return $this->belongsTo(Centers::class,'city','id');
	}

	public function getImageNameAttribute(){
        $img = unserialize($this->image);
        if (is_array($img)) {
        	return $img[0];
        } else{
    		return "";
        }
    }

}

