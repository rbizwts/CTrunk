<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use File;
use Html;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Validator;
use Datatables;
use AppHelper;
use LaraCore;
use Image;
use App\Models\Receiver;
use App\Models\Centers;
use App\Models\_List;
use Carbon;

class ReceiverController extends Controller
{
	public $call_api;

	protected $log_identifire = 'name';

	public function __construct(Request $request){
		$this->call_api = LaraCore::isAPI($request);
	}

	public function validator(array $data, $id = NULL){
		return Validator::make($data, [
			'name' => 'required', 
			'city' => 'required',
			'image' => 'required|max:10000'
		]);
	}

	public function create(){

	}

	public function index(Request $request){
		LaraCore::canOrFail('view_receiver',$request);
		if ($this->call_api) {
			$branchId = $request->header('BranchId');
			$receiver = Receiver::where(['branch_id'=>$branchId])->get();
			$data['receiver'] = $receiver;
			$response = LaraCore::MakeAPIResponse(true,200,[],"Success",$data);
			return response($response,200);
		}else{
			$branchId = (\Auth::user()->role_id == 1 ? 0 : \Auth::user()->branch->id);
			$data = array();
			LaraCore::isDataAvailable('receiver', 'receiver.create');
			$centers_options = DB::table('centers')->select(DB::raw("CONCAT(name,' - ',state) AS disp_fromat"),'id')->where(['branch_id'=>$branchId])->pluck('disp_fromat', 'id');
			$centers_options = $centers_options->all();
			$data['centers_options'] = $centers_options;
			$state = Centers::where(['branch_id'=>$branchId])->groupBy('state')->pluck('state','state')->all();
			$data['state'] = $state;
			return view('panel.receiver.index',$data);
		}
	}

	public function edit($id,Request $request){
		$branchId = (\Auth::user()->role_id == 1 ? 0 : \Auth::user()->branch->id);
		LaraCore::canOrFail('view_receiver');
		$data = array();
		$Dates = array();
		$receiver = Receiver::where(['id'=>$id, 'branch_id'=>$branchId])->with('center')->first();
		$data['receiver'] = $receiver;

		$centers_options = DB::table('centers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(name,' - ',state) AS disp_fromat"),'id')->pluck('disp_fromat', 'id');
		$centers_options = $centers_options->all();
		$state = Centers::where(['branch_id'=>$branchId])->groupBy('state')->pluck('state','state')->all();

		$data = [
			'centers_options' => $centers_options,
			'state' => $state
		];

		if($receiver->image!=''){
			$receiver->image = implode(',',unserialize($receiver->image));
		}
		if($receiver->image_token!=''){
			$receiver->image_token = implode(',',unserialize($receiver->image_token));
		}

		$data['receiver']  = $receiver;
		$receiver->full_address = $receiver->address.' '.$receiver->area.' <br>'.$receiver->center->name.' '.$receiver->center->state;
		if($request->has('type')){
			return response()->json(array(
				"status" => "success",
				"receiver"  => $receiver,
			));
		}else{
			$html = view('panel.receiver.create',$data)->render();
			return response()->json(array(
				"status" => "success",
				"modal"  => $html,
			));	
		}
	}

	public function store(Request $request,$id=""){
		LaraCore::canOrFail('add_receiver',$request);
		if ($this->call_api) {
			$validation = $this->validator($request->all(),$request->id);
			if($validation->fails()){
				$response = LaraCore::MakeAPIResponse(false,422,[],$validation->errors(),[]);
				return response($response,422);
			}else{
				$operation = $this->save($request,$id);
				$receiver = $operation['item'];
				$this->APIUpload($request, $receiver->id,'create');
				$message = $operation['message'];
				$data['item'] = $operation['item'];
				$response = LaraCore::MakeAPIResponse(true,200,[],"$message Successfully",$data);
				return response($response,200);
			}
		}else{
			$this->validator($request->all(),$request->id)->validate();
			$operation = $this->save($request,$id);
			$message = $operation['message'];
			$data['item'] = $operation['item'];
			$data['receivers'] = $operation['receivers'];
			$response = LaraCore::MakeAPIResponse("success",200,[],"$message Successfully",$data);
			return response($response,200);
		}
	}

	public function save($request,$id=""){
		$branchId = (\Auth::user()->role_id == 1 ? 0 : \Auth::user()->branch->id);
		$abbreviation_arr = array(
			'ANDAMAN AND NICOBAR ISLANDS' => 'AN',
			'ANDHRA PRADESH' => 'AP',
			'ARUNACHAL PRADESH' => 'AR',
			'ASSAM' => 'AS',
			'BIHAR' => 'BR',
			'CHHATTISGARH' => 'CG',
			'DADRA AND NAGAR HAVELI' => 'DH',
			'DAMAN AND DIU' => 'DD',
			'NEW DELHI' => 'DL',
			'GOA' => 'GA',
			'GUJARAT' => 'GJ',
			'HARYANA' => 'HR',
			'HIMACHAL PRADESH' => 'HP',
			'JAMMU & KASHMIR' => 'JK',
			'JHARKHAND' => 'JH',
			'KARNATAKA' => 'KR',
			'KERALA' => 'KL',
			'LAKSHWADEEP' => 'LD',
			'MADHYA PRADESH' => 'MP',
			'MAHARASHTRA' => 'MH',
			'MANIPUR' => 'MN',
			'MEGHALAYA' => 'ML',
			'MIZORAM' => 'MZ',
			'NAGALAND' => 'NL',
			'ORISSA' => 'OR',
			'PUNJAB' => 'PB',
			'RAJASTHAN' => 'RJ',
			'SAURASHTRA' => 'SAU',
			'SIKKIM' => 'SK',
			'TAMIL NADU' => 'TN',
			'TELANGANA' => 'TS',
			'TRIPURA' => 'TR',
			'UTTAR PRADESH' => 'UP',
			'UTTARAKHAND' => 'UK',
			'WEST BENGAL' => 'WB',
			'DUBAI' => 'DUB',
			'NEPAL' => 'NEP',
			'BANGLADESH' => 'BAN',
			'BHUTAN' => 'BTN'
		);
		
		if($request->id == null || $request->id == ""){
			if(is_numeric($request->city)){
				$inputs = array();
				$inputs['name'] = $request->name;
				$inputs['city'] = $request->city;
				$inputs['address'] = $request->address;
				$inputs['email'] = $request->email;
				$inputs['mobile'] = $request->mobile;
				$inputs['area'] = $request->area;
				$inputs['ref_no'] = $request->ref_no;
				$this->UploadFile($request, 'image', 'image_token', $inputs);
				$inputs['created_by'] = \Auth::user()->id;
				$inputs['branch_id'] = \Auth::user()->branch->id;

				$receiver = Receiver::create($inputs);
				$receivers = DB::table('receivers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(id,' - ',name) AS disp_fromat"),'id')->pluck('disp_fromat', 'id')->all();
				$receivers = implode("','",@$receivers);
				$receiver = Receiver::where(['id'=>$receiver->id, 'branch_id'=>$branchId])->with('center')->first();
				return ["message"=>"Created",'item'=>$receiver,'receivers'=>$receivers];
			}else{
				$newCenter = array();
				if(isset($abbreviation_arr[$request->state])) {
					$newCenter['abbreviation'] = $abbreviation_arr[$request->state];
				}
				$newCenter['name'] = $request->city;
				$newCenter['state'] = $request->state;
				$newCenter['zipcode'] = $request->zipcode;
				$newCenter['region_id'] = '0';
				$newCenter['created_by'] = \Auth::user()->id;
				$newCenter['branch_id'] = \Auth::user()->branch->id;
				$center = Centers::create($newCenter);

				$newReceiver = array();
				$newReceiver['name'] = $request->name;
				$newReceiver['city'] = $center->id;
				$newReceiver['address'] = $request->address;
				$newReceiver['email'] = $request->email;
				$newReceiver['mobile'] = $request->mobile;
				$newReceiver['area'] = $request->area;
				$newReceiver['ref_no'] = $request->ref_no;
				$newReceiver['created_by'] = \Auth::user()->id;
				$newReceiver['branch_id'] = \Auth::user()->branch->id;

				$this->UploadFile($request, 'image', 'image_token', $inputs);
				$receiver = Receiver::create($newReceiver);
				$receivers = DB::table('receivers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(id,' - ',name) AS disp_fromat"),'id')->pluck('disp_fromat', 'id')->all();
				$receivers = implode("','",@$receivers);
				$receiver = Receiver::where(['id'=>$receiver->id, 'branch_id'=>$branchId])->with('center')->first();
				return ["message"=>"Created",'item'=>$receiver,'receivers'=>$receivers];
			}
		}
		else{
			if(is_numeric($request->city)){
				LaraCore::canOrFail('edit_receiver',$request);
				$receiver = Receiver::where(['id'=>$request->input('id')])->with('center')->first();
				$inputs = $request->except('_token');
				$this->UploadFile($request, 'image', 'image_token', $inputs);
				$receiver->update($inputs);
				$receivers = DB::table('receivers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(id,' - ',name) AS disp_fromat"),'id')->pluck('disp_fromat', 'id')->all();
				$receivers = implode("','",@$receivers);
				$receiver = Receiver::where(['id'=>$request->input('id'), 'branch_id'=>$branchId])->with('center')->first();
				return ["message"=>"Updated",'item'=>$receiver,'receivers'=>$receivers];
			}else{
				LaraCore::canOrFail('edit_receiver',$request);
				$newCenter = array();
				if(isset($abbreviation_arr[$request->state])) {
					$newCenter['abbreviation'] = $abbreviation_arr[$request->state];
				}
				$newCenter['name'] = $request->city;
				$newCenter['state'] = $request->state;
				$newCenter['zipcode'] = $request->zipcode;
				$newCenter['region_id'] = '0';
				$newCenter['created_by'] = \Auth::user()->id;
				$newCenter['branch_id'] = \Auth::user()->branch->id;
				$center = Centers::create($newCenter);

				$newReceiver = array();
				$newReceiver['name'] = $request->name;
				$newReceiver['city'] = $center->id;
				$newReceiver['address'] = $request->address;
				$newReceiver['email'] = $request->email;
				$newReceiver['mobile'] = $request->mobile;
				$newReceiver['area'] = $request->area;
				$newReceiver['ref_no'] = $request->ref_no;
				$newReceiver['created_by'] = \Auth::user()->id;
				$newReceiver['branch_id'] = \Auth::user()->branch->id;
				$receiver = Receiver::where(['id'=>$request->input('id'), 'branch_id'=>$branchId])->with('center')->first();
				$this->UploadFile($request, 'image', 'image_token', $inputs);
				$receiver->update($newReceiver);
				$receivers = DB::table('receivers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(id,' - ',name) AS disp_fromat"),'id')->pluck('disp_fromat', 'id')->all();
				$receivers = implode("','",@$receivers);
				$receiver = Receiver::where(['id'=>$request->input('id'), 'branch_id'=>$branchId])->with('center')->first();
				return ["message"=>"Updated",'item'=>$receiver,'receivers'=>$receivers];
			}
			
		}
	}

	public function update(Request $request,$id){
		$request->merge(['id' => $id]);
		$operation = $this->save($request,$id);
		$receiver = $operation['item'];
		$this->APIUpload($request, $receiver->id,'edit');
		$message = $operation['message'];
		$data['item'] = $operation['item'];
		$data['receivers'] = $operation['receivers'];
		$response = LaraCore::MakeAPIResponse(true,200,[],"$message Successfully",$data);
		return response($response,200);
	}

	public function listReceiver(Request $request){
		$branchId = \Auth::user()->branch->id;
		LaraCore::canOrFail('view_receiver');
		$receiver = DB::table('receivers')->where(['receivers.branch_id'=>$branchId]);
		$receiver->leftJoin('centers', 'receivers.city', '=', 'centers.id');
		$receiver = $receiver->select(['receivers.*', 'centers.id as centers_id', 'centers.name as centers_name', 'centers.district as centers_district', 'centers.state as centers_state', 'centers.zipcode as centers_zipcode', 'centers.created_by as centers_created_by', 'centers.created_at as centers_created_at', 'centers.updated_at as centers_updated_at'])->orderBy('receivers.name');
		$datatables = Datatables::of($receiver)
		->editColumn('city', function($receiver){
			return $receiver->centers_name;          
		})
		->addColumn('bulk_delete', function ($receiver) {
			if(LaraCore::canOrNot('delete_receiver'))
				return '<input name="list_item" type="checkbox" onclick="displayDeleteAll(this)" class="list_item entity_chkbox minimal" value="'.$receiver->id.'">';
				return ;
			})
		->addColumn('actions', function($receiver){
			$html = '';
			if(LaraCore::canOrNot('edit_receiver') || LaraCore::canOrNot('view_receiver'))
				$html .= '<a href="javascript:void(0)" class="btn show-tooltip btn-action" onclick="SetEditReceiver(this)" data-id="'.$receiver->id.'" value="edit" data-toggle="tooltip" title="Edit"><span class="fa fa-edit"></span></a>';
				if(LaraCore::canOrNot('delete_receiver'))
					$html .= '<button  class="btn btn-action" type="button" name="remove_receiver" data-id="'.$receiver->id.'" value="delete" data-toggle="tooltip" title="Delete"><span class="fa fa-trash"></span></button>';
					return $html;
				})
		->setRowId('id');
		return $datatables->make(true);
	}

	public function destroy(Request $request){
		$id = $request->receiver_ids;
		LaraCore::canOrFail('delete_receiver');
		try{
			receiver::whereIn('id',$id)->delete();
		}catch(Exception $e){
			return response()->json(array(
				"status"  =>  "error",
				"message" =>  "Something wrongwith deletion.",
			));
		}
		return response()->json(array(
			"status" => "success",
			"message" => "Receiver Deleted Successfully",
		));
	}

	public function UploadFile($request, $file, $file_token, &$inputs){
		$receiver = NULL;
		$receiver = Receiver::where(['id'=>$request->input('id')])->first();

		$destinationPath = 'public/assets/uploads/receiver/';
		if(Input::hasFile($file) || $request->$file_token != ""){
			if($receiver != NULL && $receiver->$file != ""){
				$file_to_uploads = unserialize($receiver->$file);
				foreach($file_to_uploads as $file_to_upload){
					if(file_exists($destinationPath.$file_to_upload)){
						File::delete($destinationPath.$file_to_upload);
					}
				}
				$inputs[$file] = $inputs[$file_token] = "";
			}
			if(Input::hasFile($file)){
				\File::makeDirectory($destinationPath,0777,true,true);
				$new_files = $request->file($file);
				$new_files_src = [];
				foreach($new_files as $new_file){
					if($new_file->isValid()){
						$image = $new_file;
						$name =  $new_file_name[] = $image->getClientOriginalName();
						$extension = $image->getClientOriginalExtension();
						$file_name =  md5(uniqid().time()).'_'.$name;
						$image->move($destinationPath,$file_name);
						$new_files_src[] = $file_name;
					}
				}                
				$inputs[$file]  = serialize($new_files_src);
				$inputs[$file_token] = serialize($new_file_name);
			}elseif($request->$file_token != ""){
				$inputs[$file] = $inputs[$file_token] = "";
			}
		}else{
			if($receiver != NULL){
				$inputs[$file] = $receiver->$file;
				$inputs[$file_token] = $receiver->$file_token;
			}else{
				$inputs[$file] ="";
				$inputs[$file_token] = "";
			}
		}
	}

	public function getReceiver(Request $request){
		$branchId = (\Auth::user()->role_id == 1 ? 0 : \Auth::user()->branch->id);
		$receivers = DB::table('receivers')->where(['branch_id'=>$branchId])->select(DB::raw("CONCAT(id,' - ',name) AS disp_fromat"),'id')->pluck('disp_fromat', 'id');
		$receivers = $receivers->all();
		return response()->json(array(
			"status"  =>  "success",
			"data" =>  $receivers,
		));
	}

	public function APIUpload($request,$receiverId, $type){
		$fileStr = substr($request->image, strpos($request->image, ",")+1);
		$data = base64_decode($fileStr);
		$fileName = $request->imageName;
		$filePath =  md5(uniqid().time()).'_'.$fileName;
		$path = 'public/assets/uploads/receiver/';
		$file = file_put_contents($path.$filePath, $data);
		$receiver = Receiver::where(['id'=>$receiverId])->first();

		if($type == 'edit'){
			$file_to_uploads = unserialize($receiver->image);
			foreach($file_to_uploads as $file_to_upload){
				if(file_exists($path.$file_to_upload)){
					File::delete($path.$file_to_upload);
				}
			}
		}
		$files[] = $filePath;
		$fileNames[] = $fileName;
		
		$receiver->update([
			'image'=>serialize($files),
			'image_token'=>serialize($fileNames)
		]);
	}

	public function getReceiverList(Request $request){
		return view('panel.receiver.receiver_list');
	}

	public function postReceiverList(Request $request){
		$branchId = \Auth::user()->branch->id;
		$receiver = DB::table('receivers')->where(['receivers.branch_id'=>$branchId]);
		$receiver->leftJoin('centers', 'receivers.city', '=', 'centers.id');
		$receiver = $receiver->select(['receivers.*', 'centers.id as centers_id', 'centers.name as centers_name', 'centers.district as centers_district', 'centers.state as centers_state', 'centers.zipcode as centers_zipcode', 'centers.created_by as centers_created_by', 'centers.created_at as centers_created_at', 'centers.updated_at as centers_updated_at'])->orderBy('receivers.id', 'desc')->get();

		$centers = DB::table('centers')->where(['branch_id'=>$branchId])->select(['id', 'name'])->get();
		
		$temp = array();
		foreach ($centers as $value) {
			$temp[$value->id] = $value->name;
		}

		return response()->json(array(
			"status" => "success",
			"response_data" => $receiver,
			"centers" => $temp,
		));
	}

	public function updateReceiver(Request $request){
		$input = $request->all();
		$id = $request->id;
		$receiver = Receiver::find($id);
		if(!is_numeric($input['city'])){
			unset($input['city']);
		}
		$receiver->update($input);
		return ["message"=>"Receiver updated successfully",'item'=>$receiver];
	}
}