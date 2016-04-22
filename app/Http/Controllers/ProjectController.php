<?php namespace App\Http\Controllers;
use Validator;
use View;
use App\Category;
use App\Project;
use App\User;
use App\Goal;
use App\Reward;
use Illuminate\Http\Request;
class ProjectController extends Controller {

  /**
   * Display a listing of the resource.
   *
   * @return Response
   */

	public function getProjects(Request $request){
		$projects = Project::orderBy('id','DESC');
		if($request->is('user/projects') && $this->user){
			$projects->where('user_id',$this->user->id);
		}
		$projects = $projects->paginate(8);
		return $projects;
	}

	public function projects(Request $request,$userid=null){
		$this->layout = 'project.list';
		$this->metas['title'] = "Төслүүд";
		$edit = false;
		//Request::is('user/projects*')

		// General list
		$projects = Project::orderBy('id','DESC');

		// Public List
		if($request->is('projects')){
			$projects->where('status',1);
		}

		// User's own list
		if($request->is('user/projects') && $this->user){
			$this->metas['title'] = "Миний Төслүүд";
			$projects->where('user_id',$this->user->id);
			$edit = true;
			
		}

		// User's list for public
		if($request->is('user/profile/*/projects')){
			$user = User::getUserbyid($userid);
			$this->metas['title'] = $user->firstname." ".$user->lastname." -н Төслүүд";
			$projects->where('status',1);
		}

		$projects = $projects->paginate(8);
		
		$this->view = $this->BuildLayout();
		$this->view
			->withEdit($edit)
			->withProjects($projects)
			;
		return $this->view;
	}

	public function index(){
	}

  /**
   * Show the form for creating a new resource.
   *
   * @return Response
   */
  public function create()
  {
    
  }

	public function appendScriptStyle(){
		//for upload
		array_push($this->scripts['footer'],'upload/jquery.ui.widget.js');
		array_push($this->scripts['footer'],'upload/load-image.all.min.js');
		array_push($this->scripts['footer'],'upload/canvas-to-blob.min.js');
		array_push($this->scripts['footer'],'upload/jquery.iframe-transport.js');
		array_push($this->scripts['footer'],'upload/jquery.fileupload.js');
		array_push($this->scripts['footer'],'upload/jquery.fileupload-process.js');
		array_push($this->scripts['footer'],'upload/jquery.fileupload-image.js');
		array_push($this->styles,'jquery.fileupload.css');
		//for cke
		array_push($this->scripts['header'],'../libraries/ckeditor/ckeditor.js');
		
		array_push($this->scripts['footer'],'../libraries/bower_components/moment/min/moment.min.js');
		array_push($this->scripts['footer'],'../libraries/bower_components/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js');
		array_push($this->styles,'../libraries/bower_components/eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css');
	}
	public function add(){
		$this->layout = 'project.add';
		$this->metas['title'] = "Төсөл нэмэх";

		$this->appendScriptStyle();

		$this->view = $this->BuildLayout();

		$this->view
			->withUser($this->user)
			->withCategories(Category::getCategoryOptions(1))
			;
		return $this->view;
	}
  
	public function postNext(Request $request){
		$return =[];
		$return['status'] = false;
		$step = $request->get('step');
		switch($step){
			case 'addproject':
				$rules = [
					'title' => 'required|unique:projects',
					'slug' => 'required|unique:projects',
					'category_ids' => 'required',
				];
				$v = Validator::make($request->all(), $rules);
				if ($v->fails()){
					$return['status'] = false;
					$return['errors'] = $v->errors();
				} else {
					$project = new Project;
					$project->title = $request->get('title');
					$project->user_id = $this->user->id;
					$project->slug = $request->get('slug');
					//TODO project ids comma separated
					$project->category_ids = $request->get('category_ids');
					$project->save();
					
					$projectCategoryIds = explode(',',$project->category_ids);
					$projectCategories = [];
					foreach($projectCategoryIds as $pg){
						$projectCategories[] = Category::find($pg)->title;
					}
					
					$project->category = implode(', ',$projectCategories);
					$addprojectdetail = View::make('project.steps.addprojectdetail')
						->withProject($project)
						->render()
					;
					$return['status'] = true;
					$return['view'] = $addprojectdetail;
				}
			break;
			case 'addprojectdetail':
				$rules = [
					'image' => 'required',
					'video' => 'required',
					'intro' => 'required',
					'category_ids' => 'detail',
				];
				$v = Validator::make($request->all(), $rules);
				if ($v->fails()){
					$return['status'] = false;
					$return['errors'] = $v->errors();
				} else {
					$project = Project::find($request->get('id'));
					//Check if project is user's own
					if($this->user->id == $project->user_id){
						$project->image = $request->get('image');
						$project->intro = $request->get('intro');
						$project->video = $request->get('intro');
						$project->detail = $request->get('detail');
						$project->team_members = $request->get('team_members');
						$project->save();
					}
					$return['status'] = true;
					$return['message'] = 'Таны төсөл амжилттай шинэчилэгдлээ';
				}
			break;
		}
		
		return $return;
	}

  /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
  public function store()
  {
    
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
	public function project($slug){
		$project = Project::where('slug',$slug);
		$status = null;
		$edit = false;
		if($project->exists()){
			$project = $project->first();
			$this->metas['title'] = $project->title;
			$this->layout = 'project.view';
		} else {
			$this->metas['title'] = 'Төсөл олдсонгүй';
			$this->layout = 'errors.404';
			$status = "Таны хайсан төсөл олдсонгүй";
			$project = null;
		}
		if($this->user && $project){
			if($this->user->id == $project->user_id){
				$edit = true;
			}
		}
		$this->view = $this->BuildLayout();
		return $this->view
			->withStatus($status)
			->withEdit($edit)
			->withProject($project)
		;
	}

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
	public function edit($id){
		$this->appendScriptStyle();
		$project = Project::where('id',$id);
		$status = null;
		$edit = false;
		if($project->exists()){
			$project = $project->first();
			if($this->user->id == $project->user_id){
				$this->metas['title'] = $project->title;
				$this->layout = 'project.edit';
			} else {
				$this->metas['title'] = 'Хандах эрхгүй';
				$this->layout = 'errors.403';
				$status = "Танд уг төслийг засах эрх байхгүй байна";
			}
		} else {
			$this->metas['title'] = 'Төсөл олдсонгүй';
			$this->layout = 'errors.404';
			$status = "Таны хайсан төсөл олдсонгүй";
			$project = null;
		}
		$this->view = $this->BuildLayout();
		return $this->view
			->withCategories(Category::getCategoryOptions(1))
			->withStatus($status)
			->withEdit($edit)
			->withProject($project)
		;
	}

  /**
   * Update the specified resource in storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function update($id)
  {
    
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function destroy($id)
  {
    
  }

	public function addGoalModal(){
		$addGoalModal = view('modules.modal', ['id'=>'addgoalmodal','title' => 'Төслийн зорилт нэмэх','modalbody'=>'modules.project.goal_add'])
			->render()
		;
		$return['status'] = true;
		$return['view'] = $addGoalModal;
		return $return;
	}

	public function addGoal(Request $request){
		$rules = [
			'title' => 'required',
			'description' => 'required',
			'start' => 'required',
			'end' => 'required',
			'phase' => 'required',
			'goal' => 'required',
		];
		$v = Validator::make($request->all(), $rules);
		if ($v->fails()){
			$return['status'] = false;
			$return['errors'] = $v->errors();
		} else {
			if($request->has('project_id')){
				$project_id = $request->get('project_id');
				$project = Project::find($project_id);
				if($project && $project->user_id == $this->user->id){
					$goal = new Goal;
					$goal->title = $request->get('title');
					$goal->project_id = $request->get('project_id');
					$goal->description = $request->get('description');
					$goal->start = $request->get('start');
					$goal->end = $request->get('end');
					$goal->phase = $request->get('phase');
					$goal->goal = $request->get('goal');
					$goal->save();
					$goalDetail = View::make('modules.project.goal_list_item')
						->with('g',$goal)
						->with('remove',true)
						->render()
					;
					$return['status'] = true;
					$return['view'] = $goalDetail;
				} else {
					$return['status'] = false;
					$return['uid'] = $this->user->id;
					$return['project_id'] = $project_id;
					$return['errors'] = ['Таны төсөл биш байна'];
				}
			} else {
				$return['status'] = false;
				$return['errors'] = ['Төслийн АйДи байхгүй байна'];
			}
			
		}
		return $return;
	}

	public function removeGoal(Request $request){
		$return['status'] = false;
		$return['errors'] = [];
		if($request->has('project_id')){
			$project_id = $request->get('project_id');
			$project = Project::find($project_id);
			if($project && $project->user_id == $this->user->id){
				$goalid = $request->get('goalid');
				$goal = Goal::find($goalid);
				if ($goal->project_id == $project_id){
					$goal->delete();
					$return['status'] = true;
					$return['message'] = ['Зорилт устгагдлаа'];
				}
			} else {
				$return['status'] = false;
				$return['errors'] = ['Таны төсөл биш байна'];
			}
		} else {
			$return['status'] = false;
			$return['errors'] = ['Төслийн АйДи байхгүй байна'];
		}
		return $return;
	}

	public function addRewardModal(){
		$addRewardModal = view('modules.modal', ['id'=>'addrewardmodal','title' => 'Төслийн урамшуулал нэмэх','modalbody'=>'modules.project.reward_add'])
			->render()
		;
		$return['status'] = true;
		$return['view'] = $addRewardModal;
		return $return;
	}

	public function addReward(Request $request){
		$rules = [
			'title' => 'required',
			'description' => 'required',
			'value' => 'required',
			'amount' => 'required',
			'estimated_date' => 'required',
		];
		$v = Validator::make($request->all(), $rules);
		if ($v->fails()){
			$return['status'] = false;
			$return['errors'] = $v->errors();
		} else {
			if($request->has('project_id')){
				$project_id = $request->get('project_id');
				$project = Project::find($project_id);
				if($project && $project->user_id == $this->user->id){
					$reward = new Reward;
					$reward->title = $request->get('title');
					$reward->image = $request->get('reward_image');
					$reward->project_id = $request->get('project_id');
					$reward->description = $request->get('description');
					$reward->value = $request->get('value');
					$reward->amount = $request->get('amount');
					$reward->estimated_date = $request->get('estimated_date');
					$reward->save();
					$rewardListItem = View::make('modules.project.reward_list_item')
						->with('r',$reward)
						->with('remove',true)
						->render()
					;
					$return['status'] = true;
					$return['view'] = $rewardListItem;
				} else {
					$return['status'] = false;
					$return['uid'] = $this->user->id;
					$return['project_id'] = $project_id;
					$return['errors'] = ['Таны төсөл биш байна'];
				}
			} else {
				$return['status'] = false;
				$return['errors'] = ['Төслийн АйДи байхгүй байна'];
			}
			
		}
		return $return;
	}
	
	public function removeReward(Request $request){
		$return['status'] = false;
		$return['errors'] = [];
		if($request->has('project_id')){
			$project_id = $request->get('project_id');
			$project = Project::find($project_id);
			if($project && $project->user_id == $this->user->id){
				$rewardid = $request->get('rewardid');
				$reward = Reward::find($rewardid);
				if ($reward->project_id == $project_id){
					$reward->delete();
					$return['status'] = true;
					$return['message'] = ['Зорилт устгагдлаа'];
				}
			} else {
				$return['status'] = false;
				$return['errors'] = ['Таны төсөл биш байна'];
			}
		} else {
			$return['status'] = false;
			$return['errors'] = ['Төслийн АйДи байхгүй байна'];
		}
		return $return;
	}
}

?>