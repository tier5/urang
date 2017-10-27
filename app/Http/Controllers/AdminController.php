<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Redirect;
use App\Helper\NavBarHelper;
use Hash;
use App\Admin;
use App\SiteConfig;
use App\Neighborhood;
use App\Categories;
use App\PriceList;
use DB;
use App\User;
use App\UserDetails;
use App\CustomerCreditCardInfo;
use App\Faq;
use App\Staff;
use App\Pickupreq;
use App\PaymentKeys;
use App\Events\SendEmailOnSignUp;
use Illuminate\Support\Facades\Input;
use Session;
use App\Cms;
use App\OrderDetails;
use App\SchoolDonations;
use App\SchoolOrderDonations;
use App\PickUpNumber;
use App\Invoice;
use App\SchoolDonationPercentage;
use Intervention\Image\Facades\Image;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use App\PickUpTime;
use DateTime;
use App\OrderTracker;
use App\Coupon;
use App\IndexContent;
use App\CustomerComplaintsEmail;
use App\EmailTemplateSignUp;
use App\EmailTemplateForgetPassword;
use App\Helper\SiteHelper;
use App\EmailTemplateOrderConfirm;
use App\IndexPageWysiwyg;
use App\ref;
use App\MobileAppWys;
use App\refPercentage;
use App\NeighborhoodSeo;
use Illuminate\Support\Facades\Event;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;

class AdminController extends Controller
{
    public function index() {
        if (Auth::check()) {
            return redirect()->route('get-admin-dashboard');
        }
        else
        {
            return view('admin.login');
        }
    }
    //disbale back after logout function
    public function checkForSession(Request $request) {
        if (Auth::check()) {
            return 1;
        }
        else
        {
            return 0;
        }
    }
    public function LoginAttempt(Request $request) {
        //dd($request);
        //protected $guard = {'admin'};
        $email = $request->email;
        $password = $request->password;
        $remember_me = isset($request->remember)? true : false;
        //dd($remember_me);
        if (Auth::attempt(['email' => $email, 'password' => $password], $remember_me)) {
            return redirect()->route('get-admin-dashboard');
        }
        else
        {
            return redirect()->route('get-admin-login')->with('fail', 'wrong username or password');
        }
    }
    public function getDashboard() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $customers = User::with('user_details', 'pickup_req', 'order_details')->paginate(10);
        $school_count = SchoolDonations::count();
        //dd($school_count);
        return view('admin.dashboard', compact('user_data', 'site_details', 'customers', 'school_count'));
    }
    public function getEmailTemplates() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $customers = User::with('user_details', 'pickup_req', 'order_details')->paginate(10);
        $school_count = SchoolDonations::count();
        $complaintsEmail = CustomerComplaintsEmail::first();
        $signup_temp = EmailTemplateSignUp::first();
        $forget_pass = EmailTemplateForgetPassword::first();
        $order_confirm = EmailTemplateOrderConfirm::first();
        //dd($school_count);
        //dd($complaintsEmail);
        return view('email.complaintsTemplate', compact('user_data', 'site_details', 'customers', 'school_count','complaintsEmail','signup_temp','forget_pass','order_confirm'));
    }
    public function logout() {
        Auth::logout();
        return redirect()->route('get-admin-login');
    }
    public function getProfile() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        return view('admin.admin-profile', compact('user_data', 'site_details'));
    }
    public function postProfile(Request $request) {
        $id = Auth::user()->id;
        $password = $request->password;
        $search = Admin::find($id);
        if ($search) {
            if (Hash::check($request->user_password, $search->password)) {
                $search->username = $request->user_name;
                $search->email = $request->user_email;
                if ($search->save()) {
                   return redirect()->route('get-admin-profile')->with('success', 'records successfully updated');
                }
                else
                {
                    return redirect()->route('get-admin-profile')->with('error', 'Cannot update your details right now tray again later');
                }

            }
            else
            {
                return redirect()->route('get-admin-profile')->with('error', 'Password did not match with our record');
            }
        }
        else
        {
            return redirect()->route('get-admin-profile')->with('error', 'Could not find your details try again later');
        }
    }
    public function getSettings() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = SiteConfig::first();
        return view('admin.settings', compact('user_data', 'site_details'));
    }
    public function postChangePassword(Request $request) {
        $id = Auth::user()->id;
        $password = $request->c_pass;
        $updated_password = $request->confirm_password;
        $search = Admin::find($id);
        if ($search) {
            if (Hash::check($password, $search->password)) {
                //echo "do update";
               $search->password = bcrypt($updated_password);
               if ($search->save()) {
                   return redirect()->route('get-admin-settings')->with('success', 'password successfully updated');
                }
                else
                {
                    return redirect()->route('get-admin-settings')->with('error', 'Cannot update your password right now tray again later');
                }
            }
            else
            {
                return redirect()->route('get-admin-settings')->with('error', 'Password did not match with our record');
            }
        }
        else
        {
            return redirect()->route('get-admin-settings')->with('error', 'Could not find your details try again later');
        }
    }
    public function postSiteSettings(Request $request) {
        $site_config = SiteConfig::first();
        if ($site_config) {
            $site_config->site_title = $request->title;
            $site_config->site_url = $request->url;
            $site_config->site_email = $request->email;
            $site_config->meta_keywords = rtrim($request->metakey);
            $site_config->meta_description = rtrim($request->metades);
            if ($site_config->save()) {
               return redirect()->route('get-admin-settings')->with('success', 'site settings successfully updated');
            }
            else
            {
                return redirect()->route('get-admin-settings')->with('error', 'Could not set up site settings');
            }
        }
        else
        {
            $site_config = new SiteConfig();
            $site_config->site_title = $request->title;
            $site_config->site_url = $request->url;
            $site_config->site_email = $request->email;
            $site_config->meta_keywords = rtrim($request->metakey);
            $site_config->meta_description = rtrim($request->metades);
            if ($site_config->save()) {
                return redirect()->route('get-admin-settings')->with('success', 'site settings successfully updated');
            }
            else
            {
                return redirect()->route('get-admin-settings')->with('error', 'Could not set up site settings');
            }
        }
        
    }
    public function getNeighborhood() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $neighborhood = Neighborhood::with('admin')->paginate(10);  
        $neighborhood_seo = NeighborhoodSeo::first();
        return view('admin.neighborhood', compact('user_data', 'site_details', 'neighborhood', 'neighborhood_seo'));
    }
    public function checkSlugNeighborhood(Request $request) {
        $find = Neighborhood::where('url_slug',$request->slug)->first();
        if ($find) {
            return 0;
        } else {
            return 1;
        }
    }
    public function postNeighborhood(Request $request) {
        //dd($request);
        $name = $request->name;
        $description = $request->description;
        $slug = $request->url_slug;
        $admin_id = Auth::user()->id;
        $image = $request->image;
        $extension =$image->getClientOriginalExtension();
        $destinationPath = 'public/dump_images/';   // upload path
        $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
        $image->move($destinationPath, $fileName); // uploading file to given path 
        $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
        $img->save('public/app_images/'.$img->basename);
        $data = new Neighborhood();
        $data->admin_id = $admin_id;
        $data->name = $name;
        $data->description = $description;
        $data->image = $fileName;
        $data->url_slug = $slug;
        $data->page_title = $request->each_title;
        $data->meta_keywords = $request->each_meta_keys;
        $data->meta_description = $request->each_meta_des;
        if ($data->save()) {
           //return 1;
            return redirect()->route('get-neighborhood')->with('success', 'Neighborhood added Successfully');
        }
        else
        {
            //return 0;
            return redirect()->route('get-neighborhood')->with('fail', 'Failed to add neighborhood');
        }
    }
    public function editNeighborhood(Request $request) {
        //dd($request);
        $search = Neighborhood::find($request->id);
        if ($search) {
            $search->name = $request->nameEdit;
            $search->description = $request->descriptionEdit;
            if ($request->image) {
                $image = $request->image;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
                $search->image = $fileName;
            }
            $search->url_slug = $request->url_slug_edit;
            $search->page_title = $request->each_title_edit;
            $search->meta_keywords = $request->each_meta_keys_edit;
            $search->meta_description = $request->each_meta_des_edit;
            if ($search->save()) {
                return redirect()->route('get-neighborhood')->with('success', 'Neighborhood updated Successfully');
            }
            else
            {
                return redirect()->route('get-neighborhood')->with('fail', 'Failed to update neighborhood');
            }
        }
        else
        {
            return redirect()->route('get-neighborhood')->with('fail', 'Failed to update neighborhood');
        }
    }
    public function deleteNeighborhood(Request $request) {
        //return $request->id;
        $search = Neighborhood::find($request->id);
        if ($search) {
            if ($search->delete()) {
                $search_school = SchoolDonations::where('neighborhood_id',$request->id)->get();
                //return $search_school;
                if ($search_school) {
                    foreach ($search_school as $school) {
                       $school->delete();
                    }
                    return 1;
                }
                else
                {
                    return 1;
                }
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function getPriceList() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $priceList = PriceList::with('categories', 'admin')->paginate(10);
        $categories = Categories::all();
        //dd(count($categories));
        return view('admin.priceList', compact('user_data', 'site_details', 'priceList', 'categories'));
    }
    public function postPriceList(Request $request){
        //dd($request);
        for ($i=0; $i < count($request->category) ; $i++) { 
            //print_r($request->name[$i]);
            $item = new PriceList();
            $item->admin_id = Auth::user()->id;
            $item->category_id = $request->category[$i];
            $item->item = $request->name[$i];
            $item->price = $request->price[$i];
            /*$image = $request->image;
            $extension =$image->getClientOriginalExtension();
            $destinationPath = 'public/dump_images/';
            $fileName = rand(111111111,999999999).'.'.$extension;
            $image->move($destinationPath, $fileName);
            $item->image = $fileName;
            $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
            $img->save('public/app_images/'.$img->basename);*/
            $item->save();
        }
        return redirect()->route('getPriceList')->with('success', 'items successfully added!');
        
    }
    public function editPriceList(Request $request) {
        //return 0;
        $search = PriceList::find($request->id);
        if ($search) {
            $search->item = $request->name;
            $search->price = $request->price;
            if ($search->save()) {
                //$return =  PriceList::with('categories', 'admin')->get();
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function postDeleteItem(Request $request) {
        $search = PriceList::find($request->id);
        if ($search) {
            if ($search->delete()) {
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function postCategory(Request $request) {
        $category = new Categories();
        $category->name = $request->name;
        if ($category->save()) {
            return 1;
        }
        else
        {
            return 0;
        }
    }
    public function postDeleteCategory(Request $request) {
        $search = Categories::find($request->id);
        if ($search) {
            if ($search->delete()) {

                $price_list = PriceList::where('category_id',$request->id)->get();
                foreach ($price_list as $item) {
                    $item->delete();
                }
                return 1;
            }
            else
            {
                return 0;
            }
            
        }
        else
        {
            return 0;
        }
    }
    public function getCustomers(){
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $customers = User::with('user_details')->paginate(10);
        $refs = ref::with('user')->get();
        return view('admin.customers', compact('user_data', 'site_details', 'customers', 'refs'));
    }
    public function getEditCustomer($id) {
        $id = base64_decode($id);
        $user = User::where('id', $id)->with('user_details', 'card_details')->first();
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        return view('admin.EditCustomers', compact('user_data', 'site_details', 'user'));
    }
    public function postBlockCustomer(Request $request) {
        $id = $request->id;
        $user = User::find($id);
        if ($user && $user->block_status == 0) {
            $user->block_status = 1;
            if ($user->save()) {
               return 1;
            }
            else
            {
                return 0;
            }
        }
        elseif($user && $user->block_status == 1)
        {
            $user->block_status = 0;
            if ($user->save()) {
               return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function DeleteCustomer(Request $request) {
        $id = $request->id;
        $user = User::find($id);
        if ($user) {
            if ($user->delete()) {
                $user_details = UserDetails::where('user_id', $id)->first();
                $user_details->delete();
                $card_details = CustomerCreditCardInfo::where('user_id', $id)->first();
                if ($card_details) {
                    //$card_details->delete();
                    if ($card_details->delete()) 
                    {
                        $search = Pickupreq::where('user_id', $id)->get();
                        if($search)
                        {
                            foreach ($search as $pick_up_req) {
                                $pick_up_req->delete();
                            }
                        }
                        $search_invoice = Invoice::where('user_id', $id)->get();
                        if($search_invoice)
                        {
                            foreach ($search_invoice as $inv) {
                                $inv->delete();
                            }
                        }
                        $orders = OrderDetails::where('user_id', $id)->get();
                        if($orders)
                        {
                            foreach ($orders as $each_order) {
                                $each_order->delete();
                            }
                        }
                        return 1;
                    }
                    else
                    {
                        return "error in deleteing card details of this user";
                    }
                    //return 1;
                }
                else
                {
                    return "Cannot find this user's card details";
                }
            }
            else
            {
                return "Cannot delete that user with that id";
            }
        }
        else
        {
            return "Cannot find a user with that id";
        }
    }

    public function DeleteCustomerNew(Request $request)
    {
        $id = $request->id;
        $user = User::find($id);
        if($user)
        {
            if($user->delete())
            {
                $user_details = UserDetails::where('user_id', $id)->first();
                if($user_details->delete())
                {
                        $card_details = CustomerCreditCardInfo::where('user_id', $id)->first();
                        //return $card_details;
                        if($card_details)
                        {
                            $card_details->delete();
                        }
                        $search = Pickupreq::where('user_id', $id)->get();
                        if($search)
                        {
                            foreach ($search as $pick_up_req) {
                                $pick_up_req->delete();
                            }
                        }
                        $search_invoice = Invoice::where('user_id', $id)->get();
                        if($search_invoice)
                        {
                            foreach ($search_invoice as $inv) {
                                $inv->delete();
                            }
                        }
                        $orders = OrderDetails::where('user_id', $id)->get();
                        if($orders)
                        {
                            foreach ($orders as $each_order) {
                                $each_order->delete();
                            }
                        }
                        $order_tracker = OrderTracker::where('user_id', $id)->get();
                        if ($order_tracker) {
                            foreach($order_tracker as $track) {
                                $track->delete();
                            }
                        }
                        //$reference = ref::where('referred_person', $user->email)->first();
                        /*if ($reference) {
                            $reference->delete();
                        }*/
                        $del_ref_user =ref::where('user_id', $id)->get();
                        //return $del_ref_user;
                        if ($del_ref_user) {
                            foreach ($del_ref_user as $reff_Del) {
                                $reff_Del->delete();
                            }
                        }
                        return 1;
                }
                else
                {
                    return "Sorry cannot delete user details";
                }
                
            }
            else
            {
                return "Sorry cannot delete the user";
            }
        }
        else
        {
            return "Sorry cannot find the user ID";
        }
    }

    public function postEditCustomer(Request $request) {
        $search = User::find($request->id);
        if ($search) {
            $search->email = $request->email;
            if ($search->save()) {
                $searchUserDetails = UserDetails::where('user_id', $request->id)->first();
                if ($searchUserDetails) {
                    $searchUserDetails->name = $request->name;
                    $searchUserDetails->address_line_1 = $request->address_line_1;
                    $searchUserDetails->address_line_2 = $request->address_line_2;
                    $searchUserDetails->city = $request->city;
                    $searchUserDetails->state = $request->state;
                    $searchUserDetails->zip = $request->zip;
                    $searchUserDetails->personal_ph = $request->pph_no;
                    $searchUserDetails->cell_phone = isset($request->cph_no) ? $request->cph_no : NULL;
                    $searchUserDetails->off_phone = isset($request->oph_no) ? $request->oph_no : NULL;
                    $searchUserDetails->spcl_instructions = isset($request->spcl_instruction) ? $request->spcl_instruction : NULL;
                    $searchUserDetails->driving_instructions = isset($request->driving_instructions) ? $request->driving_instructions : NULL;
                    $searchUserDetails->referred_by = $request->ref_name;
                    if ($searchUserDetails->save()) {
                        $paymentKey = PaymentKeys::first();
                        if ($paymentKey) {
                            Stripe::setApiKey($paymentKey->transaction_key); // Stripe Secret API Key
                            $credit_info = CustomerCreditCardInfo::where('user_id', $request->id)->first();

                            $source = [
                                'object' => 'card',
                                'number' => $request->card_no,
                                'exp_month' => $request->SelectMonth,
                                'exp_year' => $request->selectYear,
                                'cvc' => $request->cvv,
                            ];

                            $charge = Charge::create([
                                "amount" => 100,    // 100 Cent = $1
                                "currency" => "usd",
                                "description" => "$1 Pre-authorization",
                                "capture" => false,
                                "source" => $source
                            ]);

                            $stripeCustomer = null;
                            if ($charge) {
                                if ($credit_info) {
                                    $stripeCustomer = Customer::retrieve($credit_info->stripe_customer_id);
                                    $oldCard = $stripeCustomer->sources->retrieve($credit_info->card_id);
                                    $oldCard->delete();
                                    $newCard = $stripeCustomer->sources->create(["source" => $source]);
                                    $stripeCustomer->default_source = $newCard->id;
                                    $stripeCustomer->save();
                                } else {
                                    $credit_info = new CustomerCreditCardInfo();
                                    $stripeCustomer = Customer::create([
                                        "email" => $user->email,
                                        "source" => $source
                                    ]);
                                }

                                $credit_info->name = $request->card_name;
                                $credit_info->card_no = $request->card_no;
                                $credit_info->cvv = isset($request->cvv) ? $request->cvv : NULL;
                                $credit_info->card_type = $request->cardType;
                                $credit_info->exp_month = $request->SelectMonth;
                                $credit_info->exp_year = $request->selectYear;
                                $credit_info->card_id = array_key_exists(0, $stripeCustomer->sources->data) ?
                                        $stripeCustomer->sources->data[0]->id : null;
                                $credit_info->card_fingerprint = array_key_exists(0, $stripeCustomer->sources->data) ?
                                        $stripeCustomer->sources->data[0]->fingerprint : null;
                                $credit_info->card_country = array_key_exists(0, $stripeCustomer->sources->data) ?
                                        $stripeCustomer->sources->data[0]->country : null;
                                $credit_info->stripe_customer_id = $stripeCustomer->id;

                                if ($credit_info->save()) {
                                    return redirect()->route('getAllCustomers')->with('successUpdate', 'Records Updated Successfully!');
                                } else {
                                    return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                                }
                            }  else {
                                return redirect()->route('get-user-profile')->with('fail', 'Invalid card, please use a valid card!');
                            }
                        } else {
                            return redirect()
                                ->back()
                                ->with('fail', "Oops! Something went wrong. Possible reason payment gateway error. Please check valid payment keys are set up.");
                        }
                    } else {
                        return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                    }
                } else {
                    return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                }
            } else {
                return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
            }
        } else {
            return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
        }
    }

    public function getAddNewCustomer(){
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        return view('admin.addnewcustomer', compact('user_data', 'site_details'));
    }
    public function postAddNewCustomer(Request $request) {
        //dd($request);
        $validator = $this->validate($request, [
            'email' => 'required|unique:users',
            'password' => 'required|min:6',
            'conf_password' => 'required|min:6|same:password',
            'name' => 'required',
            'strt_address_1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'zip' => 'required|numeric',
            'personal_ph' => 'required',
            'card_name' => 'required',
            'card_no' => 'required',
            'SelectMonth' => 'required',
            'selectYear' => 'required'
        ]);

        /*if ($validator->fails()) {
            return redirect('/add-new-customer')
                        ->withErrors($validator)
                        ->withInput();
        }*/
        //dd($request);
        $user = new User();
        $user->email = $request->email;
        /*$is_ref = ref::where('referred_person', $request->email)->first();
        if ($is_ref != null) {
            $is_ref->discount_status = 1;
            $is_ref->save();
        }*/
        $user->password = bcrypt($request->conf_password);
        $user->block_status = 0;
        if ($user->save()) {
            $user_details = new UserDetails();
            $user_details->user_id = $user->id;
            $user_details->name = $request->name;
            $user_details->address_line_1 = $request->strt_address_1;
            $user_details->address_line_2 = $request->strt_address_2;

            $user_details->personal_ph = $request->personal_ph;
            $user_details->cell_phone = isset($request->cellph_no) ? $request->cellph_no : NULL;
            $user_details->off_phone = isset($request->officeph_no) ? $request->officeph_no : NULL;
            $user_details->off_phone = isset($request->officeph_no) ? $request->officeph_no : NULL;
            $user_details->spcl_instructions = isset($request->spcl_instruction) ? $request->spcl_instruction : NULL;
            $user_details->driving_instructions = isset($request->driving_instructions) ? $request->driving_instructions : NULL;
            $user_details->city = $request->city;
            $user_details->state = $request->state;
            $user_details->zip = $request->zip;
            //$user_details->payment_status = 0;
            $user_details->referred_by = $request->ref_name;
            if ($user_details->save()) {
                //referrel
                if ($request->ref_name != null) {
                    $search_email = User::where('email', $request->ref_name)->first();
                    if ($search_email) {
                        return redirect()->route('getAddNewCustomers')->with('fail', 'referel email already exist .Please try another one')->withInput();
                    }
                    else {
                        if (filter_var($request->ref_name, FILTER_VALIDATE_EMAIL)) {
                        $user_details->referred_by = $request->ref_name;
                        //storing into ref table for future reference
                        $ref                    = new ref();
                        $ref->user_id           = $user->id;
                        $ref->referral_email    = $request->email;
                        $ref->referred_person   = $request->ref_name;
                        $ref->discount_status   = 1; //this should be 1 to get the discount
                        $ref->discount_count    = 1; //this should be 1 to get the discount
                        $ref->is_expired        = 0; //this will be 1 as soon as user will get the discount.
                        $ref->save();
                        }
                        else
                        {
                            $user_details->delete();
                            $user->delete();
                            return redirect()->route('getAddNewCustomers')->with('fail', 'referrel type should be type of email. please paste an email of the person you wana refer')->withInput();
                        }
                    }
                    
                }
                $credit_info = new CustomerCreditCardInfo();
                $credit_info->user_id = $user_details->user_id;
                $credit_info->name = $request->card_name;
                //$credit_info->card_no = $request->card_no;
                $credit_info->card_no = str_replace(' ', '', $request->card_no);
                $credit_info->card_type = $request->cardType;
                $credit_info->cvv = isset($request->cvv) ? $request->cvv : NULL;
                $credit_info->exp_month = $request->SelectMonth;
                $credit_info->exp_year = $request->selectYear;
                if ($credit_info->save()) {

                    $eventStatus = Event::fire(new SendEmailOnSignUp($request));
                    return redirect()->route('getAddNewCustomers')->with('success', 'Records saved successfully');
                }
                else
                {
                    return redirect()->route('getAddNewCustomers')->with('fail', 'Could Not save your details');
                }
            }
            else
            {
               return redirect()->route('getAddNewCustomers')->with('fail', 'Could Not save your details');
            }
        }
        else
        {
            return redirect()->route('getAddNewCustomers')->with('fail', 'Could Not save your details');
        }
    }
    public function getFaq() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $faq = Faq::with('admin_details')->paginate(10);
        //dd($faq);
        return view('admin.faq', compact('user_data', 'site_details', 'faq'));
    }
    public function postAddFaq(Request $request) {
        $admin_id = Auth::user()->id;
        $question = $request->question;
        $answer = $request->answer;
        $faq = new Faq();
        $faq->question = $question;
        $faq->answer = $answer;
        if($request->image)
        {
            $image = $request->image;
            $extension =$image->getClientOriginalExtension();
            $destinationPath = 'public/dump_images/';   // upload path
            $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
            $image->move($destinationPath, $fileName); // uploading file to given path 
            //return $fileName;
            $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
            $img->save('public/app_images/'.$img->basename);
            $faq->image = $fileName;
        }
        
        $faq->admin_id = $admin_id;
        if ($faq->save()) {
            return redirect()->route('getFaq')->with('successUpdate', 'Faq Successfully added!');
        }
        else
        {
            return redirect()->route('getFaq')->with('fail', 'Cannot Add faq try again later!');
        }
    }
    public function UpdateFaq(Request $request) {
        $id = $request->id;
        $question =$request->questionEdit;
        $answer = $request->answerEdit;
        if ($request->image != null) {
            $image = $request->image;
            $extension =$image->getClientOriginalExtension();
            $destinationPath = 'public/dump_images/';   // upload path
            $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
            $image->move($destinationPath, $fileName); // uploading file to given path 
            $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
            $img->save('public/app_images/'.$img->basename);
        }
        $faq = Faq::find($id);
        if ($faq) {
            $faq->question = $question;
            $faq->answer = $answer;
            if ($request->image != null) {
                $faq->image = $fileName;
            }
            $faq->admin_id = Auth::user()->id;
            if ($faq->save()) {
                return redirect()->route('getFaq')->with('successUpdate', 'Faq Successfully Updated!');
            }
            else
            {
                return redirect()->route('getFaq')->with('fail', 'Cannot Upadte faq try again later!');
            }
        }
        else
        {
            return redirect()->route('getFaq')->with('fail', 'Cannot Update faq try again later!');
        }
    }
    public function DeleteFaq(Request $request) {
        $id = $request->id;
        $faq = Faq::find($id);
        if ($faq) {
           if ($faq->delete()) {
               return 1;
           }
           else
           {
                return 0;
           }
        }
        else
        {
            return 0;
        }
    }
    public function getCustomerOrders() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = SiteConfig::first();
        $pickups = Pickupreq::where('order_status',1)->orderBy('id','desc')->with('user_detail','user','order_detail', 'invoice')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
        //dd($pickups);
        $donate_money_percentage = SchoolDonationPercentage::first();
        /*if (!$donate_money_percentage) {
            $donate_money_percentage = 0;
        }*/
        
        return view('admin.customerorders', compact('user_data', 'site_details','pickups', 'donate_money_percentage','checkDetails'));
    }

    public function changeOrderStatusAdmin(Request $req)
    {

        //return $req->order_status;
        if (isset($req->order_status)) {
            if ($req->order_status == 1) {
                $data['order_status'] = $req->order_status;
                $result = Pickupreq::where('id', $req->pickup_id)->update($data);
                if($result)
                {
                    $this->TrackOrder($req);
                    //return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
                    return 1;
                }
                else
                {
                    //return redirect()->route('getCustomerOrders')->with('fail', 'Failed to update Order Status!');

                    return 0;
                }
            } elseif ($req->order_status == 2) {
                $data['order_status'] = $req->order_status;
                $result = Pickupreq::where('id', $req->pickup_id)->update($data);
                if($result)
                {
                    $this->TrackOrder($req);
                    //return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
                    return 1;
                }
                else
                {
                    //return redirect()->route('getCustomerOrders')->with('fail', 'Failed to update Order Status!');
                    return 0;
                }
            } elseif ($req->order_status == 3) {
                $data['order_status'] = $req->order_status;
                $result = Pickupreq::where('id', $req->pickup_id)->update($data);
                if($result)
                {
                    $this->TrackOrder($req);
                    //return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
                    return 1;
                }
                else
                {
                    //return redirect()->route('getCustomerOrders')->with('fail', 'Failed to update Order Status!');
                    return 0;
                }
            } else {

                
                $data['order_status'] = $req->order_status;
                if ($req->payment_type == 1) {
                    //charge this card

                    $response = $this->captureAmountThroughCard($req->user_id, $req->chargable);
                    //dd($response);
                    //return $response;
                    if ($response === "I00001") {
                        $data['payment_status'] = 1;
                        $this->TrackOrder($req);
                        $result = Pickupreq::where('id', $req->pickup_id)->update($data);
                        //Session::put("success_code", "Payment Successfull!");
                        if($result)
                        {
                            //return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated and paid also!');
                            if(isset($req->actual_school_donation_id))
                            {
                                if($req->actual_school_donation_id!=null)
                                {
                                    $school_donation_actual = SchoolDonations::where('id',$req->actual_school_donation_id)->first();
                                    $school_donation_actual->actual_pending_money = $school_donation_actual->actual_pending_money+$req->actual_school_donation_amount;
                                    $school_donation_actual->save();
                                }
                                
                            }
                            return "I00001";
                        }
                        else
                        {
                            //return redirect()->route('getCustomerOrders')->with('fail', 'Failed to update Order Status!');
                            return "here is the problem";
                        }
                    }
                    else
                    {
                        //Session::put("error_code", $response);
                        return $response;
                    }
                    
                } else {

                    //do not charge
                    $paidOrNOt = Pickupreq::where('id',$req->pickup_id)->first(); 
                    //dd($paidOrNOt);
                    if ($paidOrNOt->payment_status == 1) {
                        $this->TrackOrder($req);
                        $result = Pickupreq::where('id', $req->pickup_id)->update($data);
                        if($result)
                        {
                            //return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
                            return 1;
                        }
                        else
                        {
                            //return redirect()->route('getCustomerOrders')->with('fail', 'Failed to update Order Status!');
                            return 0;
                        }
                    } else {
                        //return redirect()->route('getCustomerOrders')->with('fail', 'at first make sure payment is done!');
                        return "403";
                    }
                }
            }
        }
        else
        {
            //return redirect()->route('getCustomerOrders')->with('fail', 'Select the status from dropdown you want to update');
            return "444";
        }
    }
    //order tracker function
    public function TrackOrder($req) {
        //return $req->order_status;
        //update order tracker
        $pickupreq = Pickupreq::find($req->pickup_id);
        $find_tracker = OrderTracker::where('pick_up_req_id', $req->pickup_id)->first();
        if ($req->order_status == 2) {
            //picked up
            if ($find_tracker) {
                $find_tracker->picked_up_date = $pickupreq->updated_at->toDateString();
                $find_tracker->order_status = 2;
                $find_tracker->expected_return_date = date('Y-m-d',strtotime($pickupreq->updated_at->toDateString())+172800);
                $find_tracker->save();
            }
        }
        else if ($req->order_status == 3) {
            //dd("algo goes");
            if ($find_tracker) {
                if (!$find_tracker->picked_up_date) {
                    $find_tracker->picked_up_date = $pickupreq->updated_at->toDateString();
                }
                if (!$find_tracker->expected_return_date) {
                    $find_tracker->expected_return_date = date('Y-m-d',strtotime($pickupreq->updated_at->toDateString())+172800);
                }
                $find_tracker->order_status = 3;
                $find_tracker->final_invoice = $pickupreq->total_price;
                $find_tracker->save();
            }
        }
        else
        {
            if ($find_tracker) {
                if (!$find_tracker->picked_up_date) {
                    $find_tracker->picked_up_date = $pickupreq->updated_at->toDateString();
                }
                if (!$find_tracker->expected_return_date) {
                    $find_tracker->expected_return_date = date('Y-m-d',strtotime($pickupreq->updated_at->toDateString())+172800);
                }
                $find_tracker->final_invoice = $pickupreq->total_price;
                $find_tracker->order_status = 4;
                $find_tracker->return_date = $pickupreq->updated_at->toDateString();
                $find_tracker->save();
            }
        }
    }

    public function ChargeCard($id, $amount) {
        //fetch the record from databse
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $customer_credit_card = CustomerCreditCardInfo::where('user_id', $id)->first();
        $payment_keys = PaymentKeys::first();
        if ($payment_keys != null) {
            $merchantAuthentication->setName($payment_keys->login_id);
            $merchantAuthentication->setTransactionKey($payment_keys->transaction_key);
            // Create the payment data for a credit card
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($customer_credit_card->card_no);
            $creditCard->setExpirationDate("20".$customer_credit_card->exp_year."-".$customer_credit_card->exp_month);
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType( "authCaptureTransaction"); 
            $transactionRequestType->setAmount($amount);
            $transactionRequestType->setPayment($paymentOne);
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($merchantAuthentication);
            $request->setTransactionRequest( $transactionRequestType);
            $controller = new AnetController\CreateTransactionController($request);
            if ($payment_keys->mode == 1) {
                $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);
            }
            else
            {
                $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
            }
            //dd($response);
            if ($response != null) {
                $tresponse = $response->getTransactionResponse();
                if (($tresponse != null) && ($tresponse->getResponseCode()=="1") )   
                {
                    return "I00001";
                }
                else
                {
                    return 2;
                }
            } 
            else
            {
                return 1;
            }
        } 
        else 
        {
            return 0;
        }
    }

    public function captureAmountThroughCard($userId, $amountToBeCaptured) {
        $paymentKey = PaymentKeys::first();
        if ($paymentKey) {
            $customer = CustomerCreditCardInfo::whereUserId($userId)->first();
            Stripe::setApiKey($paymentKey->transaction_key); // Stripe Secret API Key

            try {
                $charge = Charge::create([
                    "amount" => $amountToBeCaptured * 100, // Convert $ to Cent
                    "currency" => "usd",
                    "description" => "Payment received",
                    "customer" => $customer->stripe_customer_id
                ]);
            } catch (\Stripe\Error\RateLimit $e) {
                return 3;
            } catch (\Stripe\Error\InvalidRequestError $e) {
                return 3;
            } catch (\Stripe\Error\AuthenticationError $e) {
                return 3;
            } catch (\Stripe\Error\ApiConnectionError $e) {
                return 3;
            } catch (\Stripe\Error\Error $e) {
                return 3;
            } catch (\Exception $e) {
                return 3;
            }

            if ($charge) {
                if ($charge->captured && $charge->paid) {
                    return "I00001";
                } else {
                    return 2;
                }
            } else {
                return 1;
            }
        } else {
            return 0;
        }
    }

    public function getStaffList() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = SiteConfig::first();
        $staff = Staff::paginate(15);
        return view('admin.staffs', compact('user_data', 'site_details', 'staff'));
    }
    public function postAddStaff(Request $request) {
        //dd($request);
        $insert_staff = new Staff();
        $insert_staff->user_name = $request->email;
        $insert_staff->password = bcrypt($request->password);
        $insert_staff->active = 1;
        if ($insert_staff->save()) {
            return redirect()->route('getStaffList')->with('success', 'Successfully added staff');
        }
        else
        {
            return redirect()->route('getStaffList')->with('fail', 'Sorry! Cannot add staff now please try again later.');
        }
    }

    public function postIsBlock(Request $request) {
        //return $request;
        $search = Staff::find($request->id);
        //return $search;
        if ($search) {
            $search->active == 1 ? $search->active=0 : $search->active=1;
            if ($search->save()) {
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function postEditDetailsStaff(Request $request) {
        //dd($request);
        $search = Staff::find($request->user_id);
        if ($search) {
            $search->user_name = $request->email;
            if ($search->save()) {
                return redirect()->route('getStaffList')->with('success', 'Details Saved Successfully!');
            }
            else
            {
                return redirect()->route('getStaffList')->with('fail', 'Could not save your details right now!');
            }
        }
        else
        {
            return redirect()->route('getStaffList')->with('fail', 'Could not find a user with this email!');
        }
    }
    public function postDelStaff(Request $request) {
        $search = Staff::find($request->id);
        if ($search) {
            if ($search->delete()) {
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function postChangeStaffPassword(Request $request) {
        //dd($request);
        $search = Staff::find($request->user_id);
        if ($search) {
            $search->password = bcrypt($request->con_new_pass);
            if ($search->save()) {
                return redirect()->route('getStaffList')->with('success', 'Password Updated Successfully!');
            }
            else
            {
                 return redirect()->route('getStaffList')->with('fail', 'Could not update your password right now!');
            }
        }
        else
        {
            return redirect()->route('getStaffList')->with('fail', 'Could not find a user with this email!');
        }
    }
    public function getSearchAdmin()
    {
            $search = Input::get('search');
        
            $obj = new NavBarHelper();
            $user_data = $obj->getUserData();
        
            $pickups = Pickupreq::where('id',$search)->with('user_detail','user','order_detail')->get();
            if($pickups)
            {
                Session::put('success', 'Search result found!');
                return view('admin.customerorders',compact('pickups','user_data'));
            }
            else
            {
                Session::put('error', 'Search result not found!');
                return view('admin.customerorders',compact('pickups','user_data'));
            }
            
    }

    public function getCmsIndexPage() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = IndexContent::first();
        return view('admin.cms-index', compact('user_data', 'cms_data'));
    }
    public function postSaveCmsIndex(Request $request) {
        //dd($request);
        $search = IndexContent::first();
        if ($search) {
            $search->tag_line = $request->tag;
            $search->header = $request->header;
            $search->tag_line_2 = $request->tag_2;
            $search->tag_line_3 = $request->tag_3;
            $search->tag_line_4 = $request->tag_4;
            $search->head_setion = $request->head_section;
            $search->contents = $request->content;
            $search->head_section_2 = $request->head_section_2;
            $search->contents_2 = $request->content_2;
            if ($request->image) {
                $image = $request->image;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                /*$isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);*/
                $search->image = $fileName;
            }
            
            if ($search->save()) {
                return redirect()->route('getCmsIndexPage')->with('success', 'Data Saved Successfully!');
            }
            else
            {
                return redirect()->route('getCmsIndexPage')->with('fail', 'Data Saved Successfully!');
            }
        }
        else
        {
            //new record first time
            $new_rec = new IndexContent();
            $new_rec->tag_line = $request->tag;
            $new_rec->header = $request->header;
            $new_rec->tag_line_2 = $request->tag_2;
            $new_rec->tag_line_3 = $request->tag_3;
            $new_rec->tag_line_4 = $request->tag_4;
            $new_rec->head_setion = $request->head_section;
            $new_rec->contents = $request->content;
            $new_rec->head_section_2 = $request->head_section_2;
            $new_rec->contents_2 = $request->content_2;
            if ($request->image) {
                $image = $request->image;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                /*$isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);*/
                $new_rec->image = $fileName;
            }
            
            if ($new_rec->save()) {
                return redirect()->route('getCmsIndexPage')->with('success', 'Data Saved Successfully!');
            }
            else
            {
                return redirect()->route('getCmsIndexPage')->with('fail', 'Data Saved Successfully!');
            }
        }
    }
    public function getCmsDryClean() {
        //echo "text";
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 0)->first();
        //dd($isDataExists);
        return view('admin.cms-dry-clean', compact('user_data', 'cms_data'));
    }
    public function postCmsDryClean(Request $request) {
        //dd($request);
        $isDataExists = Cms::where('identifier', 0)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->page_name = $request->page_name;
            $isDataExists->content = $request->content;
            if ($request->bgimage) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            if ($isDataExists->save()) {
                return redirect()->route('getCmsDryClean')->with('success', 'Successfully Updated');
            }
            else
            {
                return redirect()->route('getCmsDryClean')->with('fail', 'some error occured cannot save the details right now!');
            }

        }
        else
        {
            //insert new record
            $new_data = new Cms();
            $new_data->title = $request->title;
            $new_data->meta_keywords = $request->keywords;
            $new_data->meta_description = $request->description;
            $new_data->page_heading = $request->heading;
            //$new_data->tags = $request->tags;
            $new_data->page_name = $request->page_name;
            $new_data->content = $request->content;
            if ($request->bgimage != null) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $new_data->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            else
            {
                $new_data->background_image = NULL;
            }
            $new_data->identifier = 0;
            if ($new_data->save()) {
                return redirect()->route('getCmsDryClean')->with('success', 'Successfully Saved Your Data');
            }
            else
            {
                return redirect()->route('getCmsDryClean')->with('fail', 'some error occured cannot save the details right now!');
            }
        }
    }
    public function getCmsWashNFold() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 1)->first();
        return view('admin.cms-wash-n-fold', compact('user_data', 'cms_data'));
    }
    public function postCmsWashNFold(Request $request) {
        $isDataExists = Cms::where('identifier', 1)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->page_name = $request->page_name;
            $isDataExists->content = $request->content;
            if ($request->bgimage) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            if ($isDataExists->save()) {
                return redirect()->route('getCmsWashNFold')->with('success', 'Successfully Updated');
            }
            else
            {
                return redirect()->route('getCmsWashNFold')->with('fail', 'some error occured cannot save the details right now!');
            }

        }
        else
        {
            //insert new record
            $new_data = new Cms();
            $new_data->title = $request->title;
            $new_data->meta_keywords = $request->keywords;
            $new_data->meta_description = $request->description;
            $new_data->page_heading = $request->heading;
            $new_data->page_name = $request->page_name;
            $new_data->content = $request->content;
            if ($request->bgimage != null) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $new_data->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            else
            {
                $new_data->background_image = NULL;
            }
            $new_data->identifier = 1;
            if ($new_data->save()) {
                return redirect()->route('getCmsWashNFold')->with('success', 'Successfully Saved Your Data');
            }
            else
            {
                return redirect()->route('getCmsWashNFold')->with('fail', 'some error occured cannot save the details right now!');
            }
        }
    }

    public function getCorporate(){
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 2)->first();
        return view('admin.cms-corporate', compact('user_data', 'cms_data'));
    }
    public function postCorpoarte(Request $request) {
        $isDataExists = Cms::where('identifier', 2)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->page_name = $request->page_name;
            $isDataExists->content = $request->content;
            if ($request->bgimage) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            if ($isDataExists->save()) {
                return redirect()->route('getCorporate')->with('success', 'Successfully Updated');
            }
            else
            {
                return redirect()->route('getCorporate')->with('fail', 'some error occured cannot save the details right now!');
            }

        }
        else
        {
            //insert new record
            $new_data = new Cms();
            $new_data->title = $request->title;
            $new_data->meta_keywords = $request->keywords;
            $new_data->meta_description = $request->description;
            $new_data->page_heading = $request->heading;
            $new_data->page_name = $request->page_name;
            $new_data->content = $request->content;
            if ($request->bgimage != null) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $new_data->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            else
            {
                $new_data->background_image = NULL;
            }
            $new_data->identifier = 2;
            if ($new_data->save()) {
                return redirect()->route('getCorporate')->with('success', 'Successfully Saved Your Data');
            }
            else
            {
                return redirect()->route('getCorporate')->with('fail', 'some error occured cannot save the details right now!');
            }
        }
    }
    public function getTailoring() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 3)->first();
        return view('admin.cms-tailoring', compact('user_data', 'cms_data'));
    }

    public function getMobileAppPage() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 3)->first();
        return view('admin.cms-mobile-app', compact('user_data', 'cms_data'));
    }
    public function postTailoring(Request $request) {
        $isDataExists = Cms::where('identifier', 3)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->page_name = $request->page_name;
            $isDataExists->content = $request->content;
            if ($request->bgimage) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            if ($isDataExists->save()) {
                return redirect()->route('getTailoring')->with('success', 'Successfully Updated');
            }
            else
            {
                return redirect()->route('getTailoring')->with('fail', 'some error occured cannot save the details right now!');
            }

        }
        else
        {
            //insert new record
            $new_data = new Cms();
            $new_data->title = $request->title;
            $new_data->meta_keywords = $request->keywords;
            $new_data->meta_description = $request->description;
            $new_data->page_heading = $request->heading;
            $new_data->page_name = $request->page_name;
            $new_data->content = $request->content;
            if ($request->bgimage != null) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $new_data->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            else
            {
                $new_data->background_image = NULL;
            }
            $new_data->identifier = 3;
            if ($new_data->save()) {
                return redirect()->route('getTailoring')->with('success', 'Successfully Saved Your Data');
            }
            else
            {
                return redirect()->route('getTailoring')->with('fail', 'some error occured cannot save the details right now!');
            }
        }
    }
    public function getWetCleaning() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 4)->first();
        return view('admin.cms-wetcleaning', compact('user_data', 'cms_data')); 
    }
    public function postWetCleaning(Request $request) {
        $isDataExists = Cms::where('identifier', 4)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->page_name = $request->page_name;
            $isDataExists->content = $request->content;
            if ($request->bgimage) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $isDataExists->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            if ($isDataExists->save()) {
                return redirect()->route('getWetCleaning')->with('success', 'Successfully Updated');
            }
            else
            {
                return redirect()->route('getWetCleaning')->with('fail', 'some error occured cannot save the details right now!');
            }

        }
        else
        {
            //insert new record
            $new_data = new Cms();
            $new_data->title = $request->title;
            $new_data->meta_keywords = $request->keywords;
            $new_data->meta_description = $request->description;
            $new_data->page_heading = $request->heading;
            $new_data->page_name = $request->page_name;
            $new_data->content = $request->content;
            if ($request->bgimage != null) {
                $image = $request->bgimage;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';   // upload path
                $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
                $image->move($destinationPath, $fileName); // uploading file to given path 
                //return $fileName;
                $new_data->background_image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            else
            {
                $new_data->background_image = NULL;
            }
            $new_data->identifier = 4;
            if ($new_data->save()) {
                return redirect()->route('getWetCleaning')->with('success', 'Successfully Saved Your Data');
            }
            else
            {
                return redirect()->route('getWetCleaning')->with('fail', 'some error occured cannot save the details right now!');
            }
        }
    }

    public function addItemCustomAdmin(Request $request)
    {   
        //return $request; //this is firing through ajax so for debuging use return ie. view customerorders and function submitMyForm() //js
        //dd($request);
        $data = json_decode($request->list_items_json);
        $user = Pickupreq::find($request->row_id);
        //$previous_price = $user->total_price;
        $price_to_add = 0.00;
        $new_total_price = 0.00 ;
        //$total_price_table_pickup = 0.00;
        for ($i=0; $i< count($data); $i++) 
        {
            $order_details = new OrderDetails();
            $order_details->pick_up_req_id = $request->row_id;
            $order_details->user_id = $request->row_user_id;
            $order_details->price = $data[$i]->item_price;
            $order_details->items = $data[$i]->item_name;
            $order_details->quantity = $data[$i]->number_of_item;
            $order_details->payment_status = 0;
            //$total_price_table_pickup = $data[$i]->item_price*$data[$i]->number_of_item;
            //$price_to_add = ($price_to_add+($data[$i]->item_price*$data[$i]->number_of_item));
            $order_details->save();
        }
        //check for item

        for ($j=0; $j< count($data); $j++) 
        {
            $invoice_find = Invoice::where('pick_up_req_id', $request->row_id)->where('user_id', $request->row_user_id)->where('invoice_id', $request->invoice_updt)->where('list_item_id',$data[$j]->id)->first();
            if ($invoice_find) {
                $invoice_find->quantity = $data[$j]->number_of_item;
                //$user->total_price = $data[$j]->number_of_item * $data[$j]->item_price;
                $invoice_find->save();
            }
            else
            {
                $invoice = new Invoice();
                $invoice->pick_up_req_id = $request->row_id;
                $invoice->user_id = $request->row_user_id;
                $invoice->invoice_id = $request->invoice_updt;
                $invoice->price = $data[$j]->item_price;
                $invoice->item = $data[$j]->item_name;
                $invoice->quantity = $data[$j]->number_of_item;
                $invoice->list_item_id = $data[$j]->id;
                $price_to_add = $price_to_add;
                //$user->total_price = $data[$j]->number_of_item * $data[$j]->item_price;
                $invoice->save();
            }
            
        }
        //$user->total_price = $previous_price+$price_to_add;
        //$new_total_price = $price_to_add;
        if ($user->school_donation_id != null) {
            $fetch_percentage = SchoolDonationPercentage::first();
            $new_percentage = $fetch_percentage->percentage/100;
            $school = SchoolDonations::find($user->school_donation_id);
            $present_pending_money = $school->pending_money;
            $updated_pending_money = $present_pending_money+($new_total_price*$new_percentage);
            $school->pending_money = $updated_pending_money;
            $school->save();
        }
        //}
        //upadte total price here
        $user = Pickupreq::find($request->row_id);
        if ($user) {
            $user->total_price = 0;
            $last_inv = Invoice::where('invoice_id', $request->invoice_updt)->get();
            foreach ($last_inv as $inv) {
                $user->total_price += $inv->quantity*$inv->price;
            }
        }
        //$7 emergency extra
        if ($user->is_emergency == 1) {
            if ($user->total_price > 0) {
                $user->total_price +=7;
                /*if ($user->is_emergency == 1) {
                    # code...
                }*/
                $user->save();
            }
        }
        $user->discounted_value = $user->total_price;
        //return $user->total_price." ".$user->discounted_value; OK
        if ($user->sign_up_discount == 1) {
            $user->discounted_value -= $user->discounted_value * 10 /100;
            $user->save();
        }
        //return $user->total_price;
        //return $user->ref_discount;
        //return $user->total_price." ".$user->discounted_value; OK
        if ($user->ref_discount == 1) {
            $calculate_discount = new SiteHelper();
            $user->discounted_value  = $calculate_discount->updateTotalPriceOnRef($user->discounted_value);
            $user->save();
        }
        //return $user->discounted_value;
        //return $user->total_price." ".$user->discounted_value; OK
        if($user->save())
        {
            
            //dd($user);
            if ($user->coupon != null) {
                $calculate_discount = new SiteHelper();
                //return $user->total_price;
                $user->discounted_value = $calculate_discount->discountedValue($user->coupon, $user->discounted_value);
                /*if ($user->ref_discount == 1) {
                    $calculate_discount = new SiteHelper();
                    $user->discounted_value  = $calculate_discount->updateTotalPriceOnRef($discounted_value);
                }
                else
                {
                    $user->discounted_value = $discounted_value;
                }*/

                $user->save();
                //return $user->total_price." ".$user->discounted_value; OK
            }
            if($request->ajax())
            {
                return 1;
            }
            else
            {
                return redirect()->route('getCustomerOrders')->with('success', 'Order successfully updated!');

            }
            
        }
        else
        {
            if($request->ajax())
            {
                return 0;
            }
            else
            {
                return redirect()->route('getCustomerOrders')->with('error', 'Cannot update the order now!');

            }
            
        }
    }
    public function fetchInvoice(Request $request) {
        //return $request;
        $find_invoice = Invoice::where('pick_up_req_id', $request->id)->get();
        if ($find_invoice) {
            return $find_invoice;
        }
        else
        {
            return 0;
        }
    }
    public function getSchoolDonations() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $list_school = SchoolDonations::with('neighborhood')->paginate(10);
        $neighborhood = Neighborhood::all();
        $percentage = SchoolDonationPercentage::first();
        return view('admin.school-donations', compact('user_data', 'site_details', 'list_school', 'neighborhood', 'percentage'));
    }
    public function postSaveSchool(Request $request) {
        //dd($request);
        $school_data = new SchoolDonations();
        $school_data->neighborhood_id = $request->neighborhood;
        $school_data->school_name = $request->school_name;
        $image = $request->image;
        $extension =$image->getClientOriginalExtension();
        $destinationPath = 'public/dump_images/';
        $fileName = rand(111111111,999999999).'.'.$extension;
        $image->move($destinationPath, $fileName);
        $school_data->image = $fileName;
        $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
        $img->save('public/app_images/'.$img->basename);
        $school_data->pending_money = 0.00;
        $school_data->total_money_gained = 0.00;
        if ($school_data->save()) {
            return redirect()->route('getSchoolDonationsAdmin')->with('success', 'Successfully Saved School !');
        }
        else
        {
            return redirect()->route('getSchoolDonationsAdmin')->with('fail', 'Failed to Save School !');
        }
    }
    public function postEditSchool(Request $request) {
        //dd($request);
        $search = SchoolDonations::find($request->sch_id);
        if ($search) {
            $search->neighborhood_id = $request->neighborhood;
            $search->school_name = $request->school_name;
            if ($request->image) {
                $image = $request->image;
                $extension =$image->getClientOriginalExtension();
                $destinationPath = 'public/dump_images/';
                $fileName = rand(111111111,999999999).'.'.$extension;
                $image->move($destinationPath, $fileName);
                $search->image = $fileName;
                $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
                $img->save('public/app_images/'.$img->basename);
            }
            $search->actual_pending_money = $request->pending_money;
            $search->actual_total_money_gained = $request->money_gained;
            if ($search->save()) {
                return redirect()->route('getSchoolDonationsAdmin')->with('success', 'Successfully Saved School !');
            }
            else
            {
                return redirect()->route('getSchoolDonationsAdmin')->with('fail', 'Failed to update some error occured !');
            }
        }
        else
        {
            return redirect()->route('getSchoolDonationsAdmin')->with('fail', 'Failed to find a School !');
        }
    }
    public function postDeleteSchool(Request $request) {
        //return $request;
        $search_school = SchoolDonations::find($request->id);
        if ($search_school) {
            if ($search_school->delete()) {
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function postPendingMoney(Request $request) {
        $search_school = SchoolDonations::find($request->id);
        if ($search_school) {
            $total_money_gained = $search_school->actual_total_money_gained;
            $pending_money = $search_school->actual_pending_money;
            //return 1;
            $search_school->actual_total_money_gained = $total_money_gained+$pending_money;
            $search_school->actual_pending_money = 0.00;

            
            if ($search_school->save()) {

                 $schoolOrderDonations = new SchoolOrderDonations();
                 $schoolOrderDonations->school_id = $request->id;
                 $schoolOrderDonations->donation_amount = $pending_money;
                 $schoolOrderDonations->save();
              
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }

    public function manageReqNo()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $pick_up_schedule = $this->callBackPickUpTimes();
        return view('admin.manage-request-numbers', compact('user_data', 'site_details', 'pick_up_schedule'));
        
    }
    private function callBackPickUpTimes() {
        $return = array();
        for ($i=1; $i <=7 ; $i++) { 
            ${'day'.$i} = PickUpTime::where('day', $i)->first();
            $return[] = ${'day'.$i};
        }
        return $return;
    }
    public function changeWeekDayNumber(Request $req)
    {
        $search = PickUpNumber::first();
        
        //dd($req);
        $update = PickUpNumber::where('id',$search->id)->update([$req->column_name => $req->value]);

        if($update)
        {
            return redirect()->route('manageReqNo');
        }
        
    }
    public function setSundayToZero()
    {
        $search = PickUpNumber::first();
        
        //dd($search);
        $update = PickUpNumber::where('id',$search->id)->update(['sunday' => 0]);

        if($update)
        {
            return redirect()->route('manageReqNo');
        }
    }
    public function savePercentage(Request $request) {
        //return $request;
        $save_percentage = SchoolDonationPercentage::first();
        if ($save_percentage) {
            $save_percentage->percentage = $request->percentage;
            if ($save_percentage->save()) {
                return 1;
            } else {
                return 0;
            }
        } else {
            $new_percentage = new SchoolDonationPercentage();
            $new_percentage->percentage = $request->percentage;
            if ($new_percentage->save()) {
                return 1;
            } else {
                return  0;
            }
        }
    }
    private function CountOrdersPerMonth($year) {

       // $year=date("Y");

        $orders = Pickupreq::where('order_status', 4)->whereYear('created_at', '=', $year)->get();

        $jan_orders=0;
        $feb_orders=0;
        $march_orders=0;
        $april_orders=0;
        $may_orders=0;
        $june_orders=0;
        $july_orders=0;
        $aug_orders=0;
        $sep_orders=0;
        $oct_orders=0;
        $nov_orders=0;
        $dec_orders=0;
        foreach ($orders as $order) {

            switch ($order->updated_at->month) {
            case '1':
                $jan_orders++;
                //echo $jan_orders."jany";
                break;
            case '2':
                $feb_orders++;
                //echo $feb_orders;
                break;
            case '3':
                $march_orders++;
                //echo $march_orders;
                break;
            case '4':
                $april_orders++;
                //echo $april_orders;
                break;
            case '5':
                $may_orders++;
                //echo $may_orders;
                break;
            case '6':
                $june_orders++;
                //echo $june_orders;
                break;
            case '7':
                $july_orders++;
                //echo $july_orders;
                break;
            case '8':
                $aug_orders++;
                //echo $aug_orders."aug";
                break;
            case '9':
                $sep_orders++;
                //echo $sep_orders;
                break;
            case '10':
                $oct_orders++;
                //echo $oct_orders;
                break;
            case '11':
                $nov_orders++;
                //echo $nov_orders;
                break;
            case '12':
                $dec_orders++;
                //echo $dec_orders;
                break;
            default:
                echo "Something went wrong";
                break;
            }
        }
        return array(
            '1' =>  $jan_orders,
            '2' => $feb_orders,
            '3' => $march_orders,
            '4' => $april_orders,
            '5' => $may_orders,
            '6' => $june_orders,
            '7' => $july_orders,
            '8' => $aug_orders,
            '9' => $sep_orders,
            '10' => $oct_orders,
            '11' => $nov_orders,
            '12' => $dec_orders
        );
    }
    private function totalMoneyGained($year) {
    
        $orders = Pickupreq::where('order_status', 4)->whereYear('created_at', '=', $year)->get();
        $jan_price=0.00;
        $feb_price=0.00;
        $march_price=0.00;
        $april_price=0.00;
        $may_price=0.00;
        $june_price=0.00;
        $july_price=0.00;
        $aug_price=0.00;
        $sep_price=0.00;
        $oct_price=0.00;
        $nov_price=0.00;
        $dec_price=0.00;
        foreach ($orders as $order) {
            switch ($order->updated_at->month) {
            case '1':
                $jan_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value; 
                break;
            case '2':
                $feb_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '3':
                $march_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '4':
                $april_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '5':
                $may_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '6':
                $june_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '7':
                $july_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '8':
                $aug_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '9':
                $sep_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '10':
                $oct_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '11':
                $nov_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            case '12':
                $dec_price +=$order->discounted_value == NULL ? $order->total_price : $order->discounted_value;
                break;
            default:
                echo "Something went wrong";
                break;
            }
        }
        return array(
            '1' =>  $jan_price,
            '2' => $feb_price,
            '3' => $march_price,
            '4' => $april_price,
            '5' => $may_price,
            '6' => $june_price,
            '7' => $july_price,
            '8' => $aug_price,
            '9' => $sep_price,
            '10' => $oct_price,
            '11' => $nov_price,
            '12' => $dec_price
        );
    }
    private function totalSchoolDonation($year) {
        
        $schools = SchoolDonations::whereYear('updated_at', '=', $year)->get();
        //dd($schools);
        $total_money_jan = 0.00;
        $total_money_feb=0.00;
        $total_money_march=0.00;
        $total_money_april=0.00;
        $total_money_may=0.00;
        $total_money_june=0.00;
        $total_money_july=0.00;
        $total_money_aug=0.00;
        $total_money_sep=0.00;
        $total_money_oct=0.00;
        $total_money_nov=0.00;
        $total_money_dec=0.00;
        foreach ($schools as $school) {
            switch ($school->updated_at->month) {
            case '1':
                $total_money_jan += $school->actual_total_money_gained;
                //$jan_schl++;
                //echo $jan_orders."jany";
                break;
            case '2':
                $total_money_feb += $school->actual_total_money_gained;
                //$feb_schl++;
               
                break;
            case '3':
                $total_money_march += $school->actual_total_money_gained;
                //$march_schl++;
                //echo $march_orders;
                break;
            case '4':
                $total_money_april += $school->actual_total_money_gained;
                //$april_schl++;
                //echo $april_orders;
                break;
            case '5':
                $total_money_may += $school->actual_total_money_gained;
                //$may_schl++;
                //echo $may_orders;
                break;
            case '6':
                $total_money_june += $school->actual_total_money_gained;
                //$june_schl++;
                //echo $june_orders;
                break;
            case '7':
                $total_money_july += $school->actual_total_money_gained;
                //$july_schl++;
                //echo $july_orders;
                break;
            case '8':
                $total_money_aug += $school->actual_total_money_gained;
                //$aug_schl++;
                //echo $aug_orders."aug";
                break;
            case '9':
                $total_money_sep += $school->actual_total_money_gained;
                //$sep_schl++;
                //echo $sep_orders;
                break;
            case '10':
                $total_money_oct += $school->actual_total_money_gained;
                //$oct_schl++;
                //echo $oct_orders;
                break;
            case '11':
                $total_money_nov += $school->actual_total_money_gained;
                //$nov_schl++;
                //echo $nov_orders;
                break;
            case '12':
                $total_money_dec += $school->actual_total_money_gained;
                //$dec_schl++;
                //echo $dec_orders;
                break;
            default:
                echo "Something went wrong";
                break;
            }
        }
        return array(
            '1' =>  $total_money_jan,
            '2' => $total_money_feb,
            '3' => $total_money_march,
            '4' => $total_money_april,
            '5' => $total_money_may,
            '6' => $total_money_june,
            '7' => $total_money_july,
            '8' => $total_money_aug,
            '9' => $total_money_sep,
            '10' => $total_money_oct,
            '11' => $total_money_nov,
            '12' => $total_money_dec
        );
    }


    private function totalOrderSchoolDonation($year) {
        
        $schools = SchoolOrderDonations::whereYear('created_at', '=', $year)->get();
        //dd($schools);
        $total_money_jan = 0.00;
        $total_money_feb=0.00;
        $total_money_march=0.00;
        $total_money_april=0.00;
        $total_money_may=0.00;
        $total_money_june=0.00;
        $total_money_july=0.00;
        $total_money_aug=0.00;
        $total_money_sep=0.00;
        $total_money_oct=0.00;
        $total_money_nov=0.00;
        $total_money_dec=0.00;
        foreach ($schools as $school) {
            switch ($school->created_at->month) {
            case '1':
                $total_money_jan += $school->donation_amount;
                //$jan_schl++;
                break;
            case '2':
                $total_money_feb += $school->donation_amount;
                //$feb_schl++;
                break;
            case '3':
                $total_money_march += $school->donation_amount;
                //$march_schl++;
                //echo $march_orders;
                break;
            case '4':
                $total_money_april += $school->donation_amount;
                //$april_schl++;
                //echo $april_orders;
                break;
            case '5':
                $total_money_may += $school->donation_amount;
                //$may_schl++;
                //echo $may_orders;
                break;
            case '6':
                $total_money_june += $school->donation_amount;
                //$june_schl++;
                //echo $june_orders;
                break;
            case '7':
                $total_money_july += $school->donation_amount;
                //$july_schl++;
                //echo $july_orders;
                break;
            case '8':
                $total_money_aug += $school->donation_amount;
                //$aug_schl++;
                //echo $aug_orders."aug";
                break;
            case '9':
                $total_money_sep += $school->donation_amount;
                //$sep_schl++;
                //echo $sep_orders;
                break;
            case '10':
                $total_money_oct += $school->donation_amount;
                //$oct_schl++;
                //echo $oct_orders;
                break;
            case '11':
                $total_money_nov += $school->donation_amount;
                //$nov_schl++;
                //echo $nov_orders;
                break;
            case '12':
                $total_money_dec += $school->donation_amount;
                //$dec_schl++;
                //echo $dec_orders;
                break;
            default:
                echo "Something went wrong";
                break;
            }
        }
        return array(
            '1' =>  $total_money_jan,
            '2' => $total_money_feb,
            '3' => $total_money_march,
            '4' => $total_money_april,
            '5' => $total_money_may,
            '6' => $total_money_june,
            '7' => $total_money_july,
            '8' => $total_money_aug,
            '9' => $total_money_sep,
            '10' => $total_money_oct,
            '11' => $total_money_nov,
            '12' => $total_money_dec
        );
    }

    public function postExpenses(Request $request) {

        if($request->selectedyear=="")
        {
        $year=date("Y");
         $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $orders = $this->CountOrdersPerMonth($year);
        $total_money_gained = $this->totalMoneyGained($year);
       // $school_donation_monthly = $this->totalSchoolDonation($year);
        $school_donation_monthly = $this->totalOrderSchoolDonation($year);

        //dd($school_donation_monthly);
        return view('admin.monthly-expenses', compact('user_data', 'site_details', 'orders', 'total_money_gained', 'school_donation_monthly'));
        }
        else
        {
        $year=$request->selectedyear;
         $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $orders = $this->CountOrdersPerMonth($year);
        $total_money_gained = $this->totalMoneyGained($year);
        //$school_donation_monthly = $this->totalSchoolDonation($year
        $school_donation_monthly = $this->totalOrderSchoolDonation($year);
        return array($user_data, $site_details,$orders,$total_money_gained,$school_donation_monthly);
        //dd($school_donation_monthly);
       // return view('admin.monthly-expenses', compact('user_data', 'site_details', 'orders', 'total_money_gained', 'school_donation_monthly'));    
        }
       
    }
    public function getPickUpReqAdmin() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $users = User::with('user_details' , 'card_details')->get();
        $price_list = PriceList::all();
        $school_list = SchoolDonations::all();
        return view('admin.pickupreq', compact('user_data', 'site_details', 'users', 'price_list', 'school_list'));
    }
    public function postSetTime(Request $request) {
        //dd($request);
        if (strcmp($request->strt_tym, $request->end_tym) == 0) {
            return redirect()->route('manageReqNo')->with('error', 'Sorry! Start time and end time could not be same!');
        }
        else
        {
            /*$start_time = $request->strt_tym;
            $end_time  = $request->end_tym;
            $start = new DateTime($start_time);
            $end = new DateTime($end_time);
            if($start->getTimestamp() > $end->getTimestamp()) {
                return redirect()->route('manageReqNo')->with('error', 'Sorry! Start time cannot be greater than end time');
            }
            else
            {*/
                $search_first = PickUpTime::where('day', $request->day)->first();
                if ($search_first != null) {
                    $search_first->opening_time = $request->strt_tym;
                    $search_first->closing_time = $request->end_tym;
                    if ($search_first->save()) {
                        return redirect()->route('manageReqNo')->with('success', 'Time successfully saved!');
                    } else {
                        return redirect()->route('manageReqNo')->with('error', 'Sorry! could not update your details some error occurred');
                    }
                } else {
                    $pick_up_time = new PickUpTime();
                    $pick_up_time->day = $request->day;
                    $pick_up_time->opening_time = $request->strt_tym;
                    $pick_up_time->closing_time = $request->end_tym;
                    $pick_up_time->closedOrNot = 0;
                    if ($pick_up_time->save()) {
                        return redirect()->route('manageReqNo')->with('success', 'Time successfully saved!');
                    } else {
                        return redirect()->route('manageReqNo')->with('error', 'Sorry! could not save your details some error occurred');
                    }
                }
            //}
            
        }
    }
    public function setToClose(Request $request) {
        //return $request->value;
        $find = PickUpTime::where('day', $request->day)->first();
        //return $find;
        if ($find) {
            $find->closedOrNot = $request->value;
            if ($find->save()) {
                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }
    public function getCoupon() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $coupon_list = Coupon::orderBy('created_at', 'DESC')->get();
        return view('admin.coupon', compact('user_data', 'site_details', 'coupon_list'));
    }
    public function postSaveCoupon(Request $request) {
        $this->validate($request, [
            'coupon_code' => "required",
            'discount' => 'required|numeric'
        ]);
        $isDuplicate = Coupon::where('coupon_code', $request->coupon_code)->count();
        if ($isDuplicate > 0) {
            return redirect()->route('getCoupon')->with('fail', 'coupon code already exists try another one !');
        }
        else
        {
            $new_coupon = new Coupon();
            $new_coupon->coupon_code = $request->coupon_code;
            $new_coupon->discount = $request->discount;
            $new_coupon->isActive = 1;
            if ($new_coupon->save()) {
                return redirect()->route('getCoupon')->with('success', 'coupon code successfully saved!');
            } else {
               return redirect()->route('getCoupon')->with('fail', 'Something went wrong failed to save coupon !'); 
            }
        }
    }
    public function ChangeStatusCoupon(Request $request) {
        //return $request;
        $find_coupon = Coupon::find($request->id);
        if ($find_coupon) {
            $find_coupon->isActive == 0 ? $find_coupon->isActive = 1 : $find_coupon->isActive = 0;
            if ($find_coupon->save()) {
                return 1;
            }
            else
            {
                return "Could not Save Data";
            }
        }
        else
        {
            return "could not find any data associated with that id";
        }
    }
    public function postDeleteCoupon(Request $request) {
        $find_coupon = Coupon::find($request->id);
        if ($find_coupon) {
            if ($find_coupon->delete()) {
                return 1;
            }
            else
            {
                return "Unable to delete please try again later!";
            }
        }
        else
        {
            return "could not find any data associated with that id";
        }
    }
    public function getPaymentLog() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $payment_log = Pickupreq::where('payment_status', 1)->with('user_detail', 'user')->paginate(10);
        //$coupon_list = Coupon::orderBy('created_at', 'DESC')->get();
        return view('admin.payment-log', compact('user_data', 'site_details', 'payment_log'));
    }

    public function getReferralPage() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $refs = ref::paginate(20);
        $getCurrentRefPercentage = refPercentage::first();
        //$coupon_list = Coupon::orderBy('created_at', 'DESC')->get();
        return view('admin.referral', compact('user_data', 'site_details', 'refs', 'getCurrentRefPercentage'));
    }


    public function postSearchSchool(Request $request) {
        $search_result = SchoolDonations::where('school_name', 'LIKE', '%'.$request->search.'%')->groupBy('school_name')->get();
        return $search_result;
    }
    public function postSearchByButton() {
        $search = Input::get('search_school');
        $list_school = SchoolDonations::where('school_name', 'LIKE', '%'.$search.'%')->groupBy('school_name')->paginate(10);
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        //$list_school = SchoolDonations::with('neighborhood')->paginate(10);
        $neighborhood = Neighborhood::all();
        $percentage = SchoolDonationPercentage::first();
        //return view('admin.school-donations', compact('user_data', 'site_details', 'list_school', 'neighborhood', 'percentage'));
        return view('admin.school-donations')->with('list_school', $list_school)->with('user_data', $user_data)->with('site_details', $site_details)->with('neighborhood', $neighborhood)->with('percentage', $percentage);
    }

    public function postDeleteItemByID(Request $request)
    {
        //its by ajax so use return $request rather dd
        //return $request;
        //return $request->item_id.$request->user_id.$request->pick_up_id.$request->item_name;
        //return $request;
        $searchInvoice['list_item_id'] = $request->item_id;
        $searchInvoice['pick_up_req_id'] = $request->pick_up_id;
        $searchInvoice['user_id'] = $request->user_id;
        $invoice = Invoice::where($searchInvoice)->first();
        $invoiceDeletedId = $invoice->list_item_id;
        $previous_price = 0;
        $pick_up_req_id = $request->pick_up_id;

        $pickups = Pickupreq::where('id',$pick_up_req_id)->first();

        //return $pick_up_req_id;
        //return $pickups->total_price;
        $previous_price = $pickups->total_price;
        
        if($previous_price>0)
        {
            $nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;

            $total_price_to_deduct = $nowquantity * $nowPriceEach;

            $pickups->total_price = $previous_price - $total_price_to_deduct;
            $pickups->discounted_value = $pickups->total_price;

            //return $pickups->total_price;
            $pickups->save();
            
        }
        $pickups->discounted_value = $pickups->total_price;
        //return $pickups->total_price;
        if ($pickups->sign_up_discount == 1) {
            $nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;
            $total_price_to_deduct = $nowquantity * $nowPriceEach;
            //return $total_price_to_deduct;
            if ($previous_price == 0) {
                $pickups->total_price = 0;
            } else {
                $pickups->total_price = $previous_price - $total_price_to_deduct;
            }

            //return $pickups->total_price;
            $pickups->discounted_value = ($pickups->total_price - (($pickups->total_price*10)/100));
            $pickups->save();
        }
        //return $pickups->total_price;
        if ($pickups->ref_discount == 1) {
            /*$nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;
            $total_price_to_deduct = $nowquantity * $nowPriceEach;
            //return $total_price_to_deduct;
            if ($previous_price == 0) {
                $pickups->total_price = 0;
            } else {
                $pickups->total_price = $previous_price - $total_price_to_deduct;
            }*/
            
            //return $pickups->total_price;
            $pickups->discounted_value = SiteHelper::updateTotalPriceOnRef($pickups->discounted_value);
            //$pickups->discounted_value = ($pickups->total_price - (($pickups->total_price*10)/100));
            $pickups->save();
        }
        //return $pickups->total_price;
        if ($pickups->coupon != null) {

            $calculate_discount = new SiteHelper();
            $pickups->discounted_value = $calculate_discount->discountedValue($pickups->coupon, $pickups->discounted_value);
            /*if ($pickups->ref_discount == 1) {
                //return "Im here";
                $calculate_discount = new SiteHelper();
                $pickups->discounted_value  = $calculate_discount->updateTotalPriceOnRef($discounted_value);
            }
            else
            {
                $pickups->discounted_value = $discounted_value;
            }*/
            //$pickups->discounted_value = $discounted_value;
            $pickups->save();
        }
        //emergency here for $7 extra
        if ($pickups->is_emergency == 1 && $pickups->total_price == 7) {
            $pickups->total_price = 0;
            $pickups->discounted_value = 0;
            $pickups->save();      
        }

        if($invoice->delete())
        {
            $searchDetails['items'] = $request->item_name;
            $searchDetails['pick_up_req_id'] = $request->pick_up_id;
            $searchDetails['user_id'] = $request->user_id;

            $order_details = OrderDetails::where($searchDetails)->first();
            if($order_details)
            {
                $order_details->delete();
            }
            Session::put("openTheModal",true);
            Session::put("ModalToOpenOnPageLoad",$request->pick_up_id);
            /*Session::put("NextPageModal",$request->nextPageModal);*/
            if($request->nextPageModal=="modal")
            {
                Session::put("modal",true);
                //Session::put("function",false);
            }
            else if($request->nextPageModal=="function")
            {
                Session::put("function",true);
                //Session::put("modal",false);
            }
            return $invoiceDeletedId;
        }
        else
        {
            return 0;
        }
        
    }

    public function deleteItemFromInvoice(Request $request)
    {
        //$obj = new SiteHelper();
        $previous_price = 0;

        $pick_up_req_id = $request->pickupid;
        $pickups = Pickupreq::where('id',$pick_up_req_id)->first();

        //return $pick_up_req_id;

        $previous_price = $pickups->total_price;
        


        $invoice = Invoice::where('custom_item_add_id',$request->custom_item_add_id)->first();

        if($previous_price>0)
        {
            $nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;

            $total_price_to_deduct = $nowquantity * $nowPriceEach;

            $pickups->total_price = $previous_price - $total_price_to_deduct;
            $pickups->discounted_value = $pickups->total_price;

            $pickups->save();

        }
        //return $pickups->total_price." ".$pickups->discounted_value; OK
        if ($pickups->sign_up_discount == 1) {
            $nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;
            $total_price_to_deduct = $nowquantity * $nowPriceEach;

            $pickups->total_price = $previous_price - $total_price_to_deduct;
            $pickups->discounted_value = SiteHelper::updateTotalPriceOnRef($pickups->total_price);
            //$pickups->discounted_value = ($pickups->total_price - (($pickups->total_price*10)/100));
            $pickups->save();
        }
        //return $pickups->total_price." ".$pickups->discounted_value; OK
        if ($pickups->ref_discount == 1) {
            $nowquantity = $invoice->quantity;
            $nowPriceEach = $invoice->price;
            $total_price_to_deduct = $nowquantity * $nowPriceEach;
            
            $pickups->total_price = $previous_price - $total_price_to_deduct;
            $pickups->discounted_value = SiteHelper::updateTotalPriceOnRef($pickups->discounted_value);
            //$pickups->discounted_value = ($pickups->total_price - (($pickups->total_price*10)/100));
            $pickups->save();
        }
        //return $pickups->total_price." ".$pickups->discounted_value; OK
        if ($pickups->coupon != null) {
            $calculate_discount = new SiteHelper();
            $pickups->discounted_value = $calculate_discount->discountedValue($pickups->coupon, $pickups->discounted_value);
            //return $pickups->total_price." ".$discounted_value; OK
            // redundant block
            /*if ($pickups->ref_discount == 1) {
                $calculate_discount = new SiteHelper();
                $pickups->discounted_value  = $calculate_discount->updateTotalPriceOnRef($discounted_value);
            }
            else
            {
                $pickups->discounted_value = $discounted_value;
            }*/
            //$pickups->discounted_value = $discounted_value;
            $pickups->save();
            //$pickups->discounted_value = $discounted_value;
            //$pickups->save();
            //return $pickups->total_price." ".$pickups->discounted_value;
        }

        if($invoice->delete())
        {
            Session::put("openTheModal",true);
            Session::put("ModalToOpenOnPageLoad",$request->pickupid);
            Session::put("function",true);
            return 1;
        }
        else
        {
            return "Some thing went wrong!";
        }
    }

    public function postDeleteTotalPickUp(Request $request) {
        $id_to_del = $request->id;
        //dd($request);
        $search = Pickupreq::find($id_to_del);
        $trackOrder = OrderTracker::where('pick_up_req_id',$request->id)->first();
        if ($search) {
            
            if($request->schoolDonationAmount)
            {

                if($search->order_status==1)
                {
                    
                    $schoolDonationToDeletePending = SchoolDonations::find($search->school_donation_id);
                    if($schoolDonationToDeletePending)
                    {
                        $previous_pendingMoney = $schoolDonationToDeletePending->pending_money;
                        //dd($previous_pendingMoney);
                        if($previous_pendingMoney>0)
                        {
                            $now_pendingMoney = $previous_pendingMoney - $request->schoolDonationAmount;
                            $schoolDonationToDeletePending->pending_money = $now_pendingMoney;
                            $schoolDonationToDeletePending->save();
                            //dd("$schoolDonationToDeletePending");
                        }
                    }
                    
                    
                    
                }
            }
           if ($search->pick_up_type == 0) {
                $search->delete();
                $search_order_details = OrderDetails::where('pick_up_req_id', $id_to_del)->get();
                foreach ($search_order_details as $details) {
                    $details->delete();
                }
                if($trackOrder->delete())
                {
                    return redirect()->route('getCustomerOrders')->with('success', 'Order deleted successfully');
                }
                
           }
           else
           {
                if ($search->delete()) {
                    if($trackOrder->delete())
                    {
                        return redirect()->route('getCustomerOrders')->with('success', 'Order deleted successfully');
                    }
                }
                else
                {
                    return redirect()->route('getCustomerOrders')->with('error', 'Cannot delete the order now!');
                }
           }
           $invoices = Invoice::where('pick_up_req_id', $id_to_del)->get();
           if($invoice)
           {
                foreach ($invoices as $invoice) {
                    $invoice->delete();
                }
           }
        }
        else
        {
           return redirect()->route('getCustomerOrders')->with('error', 'Cannot delete the order now!');
        }
    }

    public function postComplaintsEmailChange(Request $request)
    {
        //dd($request);
        $field_to_update = $request->field_to_update;
        $customer_complaints = CustomerComplaintsEmail::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('getEmailTemplates');
    }

    public function postSignUpEmailChange(Request $request)
    {
        $field_to_update = $request->field_to_update;
        $customer_complaints = EmailTemplateSignUp::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('getEmailTemplates');
    }

    public function postForgotPasswordEmailChange(Request $request)
    {
        $field_to_update = $request->field_to_update;
        $customer_complaints = EmailTemplateForgetPassword::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('getEmailTemplates');
    }

    public function postCancleDeleteItemByID(Request $request)
    {
        $pickupreq = Pickupreq::find($request->pick_up_req_id);
        if($pickupreq->delete())
        {
            $trackOrder = OrderTracker::where('pick_up_req_id',$request->pick_up_req_id)->first();
            if($trackOrder->delete())
            {
                return 1;
            }
            else
            {
                return 0;
            }
            
        }
        else
        {
            return 0;
        }
    }

    public function postOrderConfirmEmailChange(Request $request)
    {
        $field_to_update = $request->field_to_update;
        $customer_complaints = EmailTemplateOrderConfirm::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('getEmailTemplates');
    }

    public function showIndexWysiwygControl()
    {
        $obj = new NavBarHelper();
        $site_details = $obj->siteData();
        $cms =  IndexContent::first();
        $indexcontent = IndexPageWysiwyg::first();
        return view('admin.cms-index-wysiwyg', compact('site_details', 'cms','indexcontent'));
    }

    public function postIndexWysiwygChange(Request $request)
    {
        $field_to_update = $request->field_to_update;
        $customer_complaints = IndexPageWysiwyg::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('showIndexWysiwygControl');
    }

    public function postIndexWysiwygChangeIndexContent(Request $request)
    {
        $field_to_update = $request->field_to_update;
        $customer_complaints = IndexContent::first();
        $customer_complaints->$field_to_update = $request->value;
        $customer_complaints->save();
        return redirect()->route('showIndexWysiwygControl');
    }

    public function getSortAdmin()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $input = Input::get('sort');
        //dd($input);
        $sort = isset($input) ? $input : false;
        //dd($sort);
        if($sort)
        {
            if ($sort == 'paid') 
            {
                return redirect()->route('orderSortPaid');
            } 
            else if($sort == 'unpaid') 
            {
                return redirect()->route('orderSortUnpaid');
            }
            else if ($sort == 'delivered') 
            {
                return redirect()->route('orderSortDelivered');
            }
            else if($sort == 'is_Emergency') {

                return redirect()->route('orderSortEmergency');
            }
            else if($sort == 'total_price')
            {
                return redirect()->route('orderSortTotalPrice');
            }
            else if($sort == 'pick_up_date')
            {
                return redirect()->route('orderSortPickUpDate');
            }
            else if($sort == 'created_at')
            {
                return redirect()->route('orderSortCreatedAt');
            }
            else {
                $pickups = Pickupreq::orderBy($sort,'desc')->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
                return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
            }
        }
        else
        {
            return redirect()->route('getCustomerOrders');
        }

    }

    public function orderSortDelivered()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::where('order_status', 4)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        
        return view('admin.customerorders_delivered',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortPaid()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::where('payment_status', 1)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortUnpaid()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::where('payment_status', 0)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortEmergency()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::where('is_emergency', 1)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortTotalPrice()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::orderBy('total_price','desc')->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortPickUpDate()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::orderBy('pick_up_date','desc')->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }

    public function orderSortCreatedAt()
    {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();

        $pickups = Pickupreq::orderBy('created_at','desc')->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
    }
    public function postStickyText(Request $request) {
        //dd($request);
        $save_data =  IndexPageWysiwyg::first();
        $save_data->sticky_note_text = $request->note;
        if (isset($request->disable_sticky_note)) {
            $save_data->is_sticky_active = 1;
        }
        else
        {
            $save_data->is_sticky_active = 0;
        }
        $save_data->save();
        return redirect()->route('showIndexWysiwygControl');
    }
    public function getMobilePageWyswig() {
        $data = MobileAppWys::first();
        return view('admin.mobile-app-wysiwyg', compact('data'));
    }
    public function postMobileAppWysiwyg(Request $request) {
        $isData = MobileAppWys::first();
        $newData = new MobileAppWys();
        if ($isData) {
            return $this->insertRecord($isData, $request);
        } else {
            return $this->insertRecord($newData, $request);
        }
    }
    public function insertRecord($object, $request) {

       //==============================save records ==============================//
        $object->title                  = isset($request->title) ? $request->title : $object->title;
        $object->tagLine                = isset($request->tagLine) ? $request->tagLine : $object->tagLine;
        $object->above_title            = isset($request->above_title) ? $request->above_title : $object->above_title;
        $object->description_android    = isset($request->descriptionAndroid) ? $request->descriptionAndroid : $object->description_android;
        if ($request->imageChooser) {
            $image = $request->imageChooser;
            $extension =$image->getClientOriginalExtension();
            $destinationPath = 'public/dump_images/';
            $fileName = rand(111111111,999999999).'.'.$extension;
            $image->move($destinationPath, $fileName);
            $object->image_android = $fileName;
        } else {
            $object->image_android = $object->image_android;
        }
        if ($object->save()) {
            return redirect()->route('getMobilePageWyswig')->with('success', "Records saved successfully!");
        } else {
            return redirect()->route('getMobilePageWyswig')->with('fail', "Cannot save data some error occured!");
        }
    }

    public function deactivateReferral(Request $request)
    {
        $ref = ref::find($request->id);
        $ref->discount_status  = 0;
        $ref->is_expired = 1;
        $ref->discount_count = 0;
        if($ref->save())
        {
            return 1;
        }
        else
        {
            return "Sorry cannot change the referral to deactivated right now!";
        }
        
    }

    public function activateReferral(Request $request)
    {
        $ref = ref::find($request->id);
        $ref->is_expired  = 0;
        $ref->discount_status = 1;
        $ref->discount_count = 1;
        if($ref->save())
        {
            return 1;
        }
        else
        {
            return "Sorry cannot change the referral to activated right now!";
        }
    }

    public function postMobileAppWysiwygMetaData(Request $request)
    {
        //return $request->fieldToUpdate;
        $field_to_update = $request->fieldToUpdate;
        $customer_complaints = MobileAppWys::first();
        $customer_complaints->$field_to_update = $request->value;
        if($customer_complaints->save())
        {
            return "Changed Successfully.";
        }
        else
        {
            return 0;
        }
        
    }

    public function changeRefOfferCount(Request $request)
    {
        //return $request->value;
        $ref = ref::find($request->id);
        $ref->discount_count = $request->value;
        if($ref->save())
        {
            return 1;
        }
        else
        {
            return "Sorry cannot change the count now!";
        }
    }
    public function postSavePercentageRef(Request $request) {
        $isDataExist = refPercentage::first();
        if ($isDataExist) {
            $isDataExist->percentage = $request->percentage;
            if($isDataExist->save()) {
                return 1;
            } else {
                return "Error while updating previous record";
            }
        } else {
            //no data create new 
            $setPercentageRef = new refPercentage();
            $setPercentageRef->percentage = $request->percentage;
            if ($setPercentageRef->save()) {
                return 1;
            } else {
                return "Error while inserting new record";
            }
        }
    }
    public function postSeoNeighborhood(Request $request){
        //dd($request);
        $find_record = NeighborhoodSeo::first();
        if ($find_record) {
            //update rec
            $find_record->page_title = isset($request->page_title) ? $request->page_title : $find_record->page_title;
            $find_record->meta_keywords = isset($request->meta_keys) ? $request->meta_keys : $find_record->meta_keywords;
            $find_record->meta_description = isset($request->meta_des) ? $request->meta_des : $find_record->meta_description;
            if($find_record->save()) {
                return redirect()->route('get-neighborhood')->with('success', "Record successfully updated!");
            } else {
                return redirect()->route('get-neighborhood')->with('fail', "error while saving the record!");
            }
        } else {
            //new rec goes here
            $new_rec = new NeighborhoodSeo();
            $new_rec->page_title = $request->page_title;
            $new_rec->meta_keywords = $request->meta_keys;
            $new_rec->meta_description = $request->meta_des;
            if($new_rec->save()) {
                return redirect()->route('get-neighborhood')->with('success', "Record successfully added!");
            } else {
                return redirect()->route('get-neighborhood')->with('fail', "error while saving the record!");
            }
        }
    }
}
