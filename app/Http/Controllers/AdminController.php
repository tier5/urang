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
use Illuminate\Support\Facades\Input;
use Session;
use App\Cms;
use App\OrderDetails;
use App\SchoolDonations;
use App\PickUpNumber;
use App\Invoice;
use App\SchoolDonationPercentage;
use Intervention\Image\Facades\Image;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use App\PickUpTime;
use DateTime;
use App\OrderTracker;
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
        return view('admin.dashboard', compact('user_data', 'site_details', 'customers'));
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
        //dd($neighborhood);
        return view('admin.neighborhood', compact('user_data', 'site_details', 'neighborhood'));
    }
    public function postNeighborhood(Request $request) {
        $name = $request->name;
        $description = $request->description;
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
        
        return view('admin.customers', compact('user_data', 'site_details', 'customers'));
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
                    if ($card_details->delete()) {
                        $search = Pickupreq::where('user_id', $id)->get();
                        foreach ($search as $pick_up_req) {
                            $pick_up_req->delete();
                        }
                        $search_invoice = Invoice::where('user_id', $id)->get();
                        foreach ($search_invoice as $inv) {
                            $inv->delete();
                        }
                        $orders = OrderDetails::where('user_id', $id)->get();
                        foreach ($orders as $each_order) {
                            $each_order->delete();
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
    public function postEditCustomer(Request $request) {
        //dd($request);
        $search = User::find($request->id);
        if ($search) {
            $search->email = $request->email;
            if ($search->save()) {
                $searchUserDetails = UserDetails::where('user_id', $request->id)->first();
                if ($searchUserDetails) {
                    $searchUserDetails->name = $request->name;
                    $searchUserDetails->address = $request->address;
                    $searchUserDetails->personal_ph = $request->pph_no;
                    $searchUserDetails->cell_phone = isset($request->cph_no) ? $request->cph_no : NULL;
                    $searchUserDetails->off_phone = isset($request->oph_no) ? $request->oph_no : NULL;
                    $searchUserDetails->spcl_instructions = isset($request->spcl_instruction) ? $request->spcl_instruction : NULL;
                    $searchUserDetails->driving_instructions = isset($request->driving_instructions) ? $request->driving_instructions : NULL;
                    if ($searchUserDetails->save()) {
                       $credit_info = CustomerCreditCardInfo::where('user_id', $request->id)->first();
                       if ($credit_info) {
                          $credit_info->name = $request->card_name;
                          $credit_info->card_no = $request->card_no;
                          $credit_info->cvv = isset($request->cvv) ? $request->cvv : NULL;
                          $credit_info->card_type = $request->cardType;
                          $credit_info->exp_month = $request->SelectMonth;
                          $credit_info->exp_year = $request->selectYear;
                          if ($credit_info->save()) {
                             return redirect()->route('getAllCustomers')->with('successUpdate', 'Records Updated Successfully!');
                          }
                          else
                          {
                            return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                          }
                       }
                       else
                       {
                        return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                       }
                    }
                    else
                    {
                        return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                    }
                }
                else
                {
                    return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
                }
            }
            else
            {
                return redirect()->route('getAllCustomers')->with('fail', 'Could Not find a customer to update details');
            }
        }
        else
        {
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
        $user = new User();
        $user->email = $request->email;
        $user->password = bcrypt($request->conf_password);
        $user->block_status = 0;
        if ($user->save()) {
            $user_details = new UserDetails();
            $user_details->user_id = $user->id;
            $user_details->name = $request->name;
            $user_details->address = $request->address;
            $user_details->personal_ph = $request->personal_ph;
            $user_details->cell_phone = isset($request->cellph_no) ? $request->cellph_no : NULL;
            $user_details->off_phone = isset($request->officeph_no) ? $request->officeph_no : NULL;
            $user_details->off_phone = isset($request->officeph_no) ? $request->officeph_no : NULL;
            $user_details->spcl_instructions = isset($request->spcl_instruction) ? $request->spcl_instruction : NULL;
            $user_details->driving_instructions = isset($request->driving_instructions) ? $request->driving_instructions : NULL;
            //$user_details->payment_status = 0;
            if ($user_details->save()) {
                $credit_info = new CustomerCreditCardInfo();
                $credit_info->user_id = $user_details->user_id;
                $credit_info->name = $request->card_name;
                $credit_info->card_no = $request->card_no;
                $credit_info->card_type = $request->cardType;
                $credit_info->cvv = isset($request->cvv) ? $request->cvv : NULL;
                $credit_info->exp_month = $request->SelectMonth;
                $credit_info->exp_year = $request->selectYear;
                if ($credit_info->save()) {
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
        $image = $request->image;
        $extension =$image->getClientOriginalExtension();
        $destinationPath = 'public/dump_images/';   // upload path
        $fileName = rand(111111111,999999999).'.'.$extension; // renameing image
        $image->move($destinationPath, $fileName); // uploading file to given path 
        //return $fileName;
        $img = Image::make('public/dump_images/'.$fileName)->resize(250, 150);
        $img->save('public/app_images/'.$img->basename);
        $faq = new Faq();
        $faq->question = $question;
        $faq->answer = $answer;
        $faq->image = $fileName;
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
        $pickups = Pickupreq::orderBy('id','desc')->with('user_detail','user','order_detail', 'invoice')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
        //dd($pickups);
        $donate_money_percentage = SchoolDonationPercentage::first();
        return view('admin.customerorders', compact('user_data', 'site_details','pickups', 'donate_money_percentage'));
    }

    public function changeOrderStatusAdmin(Request $req)
    {
        $total_price = isset($req->total_price)? $req->total_price : false;
        if($total_price)
        {
            $data['order_status'] = $req->order_status;
            $data['total_price'] = $total_price;
            if ($req->order_status == 4 && $req->payment_type == 1) {
                $response = $this->ChargeCard($req->user_id, $req->chargable);
                //dd($response);
                if ($response == "I00001") {
                    $data['payment_status'] = 1;
                }
                else
                {
                    Session::put("error_code", $response);
                }
            }
            $result = Pickupreq::where('id', $req->pickup_id)->update($data);
            if($result)
            {
                $this->TrackOrder($req);
                return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
            }
            else
            {
                return redirect()->route('getCustomerOrders')->with('error', 'Failed to update Order Status!');
            }
        }
        else
        {
            $data['order_status'] = $req->order_status;
            if ($req->order_status == 4 && $req->payment_type == 1) {
                $response = $this->ChargeCard($req->user_id, $req->chargable);
                //dd($response);
                if ($response == "I00001") {
                    $data['payment_status'] = 1;
                } 
                else
                {
                    Session::put("error_code", $response);
                }
            }
            //dd($data);
            $result = Pickupreq::where('id', $req->pickup_id)->update($data);
            if($result)
            {
                $this->TrackOrder($req);
                return redirect()->route('getCustomerOrders')->with('success', 'Order Status successfully updated!');
            }
            else
            {
                return redirect()->route('getCustomerOrders')->with('error', 'Failed to update Order Status!');
            }
        }
    }
    //order tracker function
    public function TrackOrder($req) {
        //update order tracker
        $pickupreq = Pickupreq::find($req->pickup_id);
        if ($req->order_status == 2) {
            //picked up
            $find_tracker = OrderTracker::where('pick_up_req_id', $req->pickup_id)->first();
            if ($find_tracker) {
                $find_tracker->picked_up_date = $pickupreq->updated_at->toDateString();
                $find_tracker->order_status = 2;
                $find_tracker->expected_return_date = date('Y-m-d',strtotime($pickupreq->updated_at->toDateString())+172800);
                $find_tracker->save();
            }
        }
        else if ($req->order_status == 3) {
            //process pickup
            $find_tracker = OrderTracker::where('pick_up_req_id', $req->pickup_id)->first();
            if ($find_tracker) {
                $find_tracker->order_status = 3;
                $find_tracker->final_invoice = $pickupreq->total_price;
                $find_tracker->save();
            }
        }
        else
        {
            $find_tracker = OrderTracker::where('pick_up_req_id', $req->pickup_id)->first();
            if ($find_tracker) {
                $find_tracker->order_status = 4;
                $find_tracker->return_date = $pickupreq->updated_at->toDateString();
                $find_tracker->save();
            }
        }
    }
    private function ChargeCard($id, $amount) {
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
            if ($sort == 'paid') {
                $pickups = Pickupreq::where('payment_status', 1)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
                return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
            } else if($sort == 'unpaid') {
                $pickups = Pickupreq::where('payment_status', 0)->with('user_detail','user','order_detail')->paginate((new \App\Helper\ConstantsHelper)->getPagination());
                $donate_money_percentage = SchoolDonationPercentage::first();
                return view('admin.customerorders',compact('pickups','user_data', 'donate_money_percentage', 'user_data', 'site_details'));
            } else {
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
    public function getCmsDryClean() {
        //echo "text";
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $cms_data = Cms::where('identifier', 0)->first();
        //dd($isDataExists);
        return view('admin.cms-dry-clean', compact('user_data', 'cms_data'));
    }
    public function postCmsDryClean(Request $request) {
        //dd($request->bgimage);
        $isDataExists = Cms::where('identifier', 0)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->tags = $request->tags;
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
            $new_data->tags = $request->tags;
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
            $isDataExists->tags = $request->tags;
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
            $new_data->tags = $request->tags;
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
            $isDataExists->tags = $request->tags;
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
            $new_data->tags = $request->tags;
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
    public function postTailoring(Request $request) {
        $isDataExists = Cms::where('identifier', 3)->first();
        if ($isDataExists != null) {
            //upadte data
            $isDataExists->title = $request->title;
            $isDataExists->meta_keywords = $request->keywords;
            $isDataExists->meta_description = $request->description;
            $isDataExists->page_heading = $request->heading;
            $isDataExists->tags = $request->tags;
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
            $new_data->tags = $request->tags;
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
            $isDataExists->tags = $request->tags;
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
            $new_data->tags = $request->tags;
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
        //dd($request);
        $data = json_decode($request->list_items_json);
        $user = Pickupreq::find($request->row_id);
        $previous_price = $user->total_price;
        $price_to_add = 0.00;
        $new_total_price = 0.00 ;
        for ($i=0; $i< count($data); $i++) 
        {
            $order_details = new OrderDetails();
            $order_details->pick_up_req_id = $request->row_id;
            $order_details->user_id = $request->row_user_id;
            $order_details->price = $data[$i]->item_price;
            $order_details->items = $data[$i]->item_name;
            $order_details->quantity = $data[$i]->number_of_item;
            $order_details->payment_status = 0;

            $price_to_add = ($price_to_add+($data[$i]->item_price*$data[$i]->number_of_item));
            $order_details->save();
        }
        for ($j=0; $j< count($data); $j++) 
        {
            $invoice = new Invoice();
            $invoice->pick_up_req_id = $request->row_id;
            $invoice->user_id = $request->row_user_id;
            $invoice->invoice_id = $request->invoice_updt;
            $invoice->price = $data[$j]->item_price;
            $invoice->item = $data[$j]->item_name;
            $invoice->quantity = $data[$j]->number_of_item;
            $price_to_add = $price_to_add;
            $invoice->save();
        }
        $user->total_price = $previous_price+$price_to_add;
        $new_total_price = $price_to_add;
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
        if($user->save())
        {
            return redirect()->route('getCustomerOrders')->with('success', 'Order successfully updated!');
        }
        else
        {
            return redirect()->route('getCustomerOrders')->with('error', 'Cannot update the order now!');
        }
    }
    public function fetchInvoice(Request $request) {
        //return $request;
        $find_invoice = Invoice::where('pick_up_req_id', $request->id)->first();
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
            $search->pending_money = $request->pending_money;
            $search->total_money_gained = $request->total_money_gained;
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
            $total_money_gained = $search_school->total_money_gained;
            $pending_money = $search_school->pending_money;
            //return 1;
            $search_school->total_money_gained = $total_money_gained+$pending_money;
            $search_school->pending_money = 0.00;
            if ($search_school->save()) {
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
    private function CountOrdersPerMonth() {
        $orders = Pickupreq::all();
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
            switch ($order->created_at->month) {
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
    private function totalMoneyGained() {
        $orders = Pickupreq::all();
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
            switch ($order->created_at->month) {
            case '1':
                $jan_price +=$order->total_price; 
                break;
            case '2':
                $feb_price +=$order->total_price;
                break;
            case '3':
                $march_price +=$order->total_price;
                break;
            case '4':
                $april_price +=$order->total_price;
                break;
            case '5':
                $may_price +=$order->total_price;
                break;
            case '6':
                $june_price +=$order->total_price;
                break;
            case '7':
                $july_price +=$order->total_price;
                break;
            case '8':
                $aug_price +=$order->total_price;
                break;
            case '9':
                $sep_price +=$order->total_price;
                break;
            case '10':
                $oct_price +=$order->total_price;
                break;
            case '11':
                $nov_price +=$order->total_price;
                break;
            case '12':
                $dec_price +=$order->total_price;
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
    private function totalSchoolDonation() {
        $schools = SchoolDonations::all();
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
                $total_money_jan += $school->total_money_gained;
                //$jan_schl++;
                //echo $jan_orders."jany";
                break;
            case '2':
                $total_money_feb += $school->total_money_gained;
                //$feb_schl++;
                //echo $feb_orders;
                break;
            case '3':
                $total_money_march += $school->total_money_gained;
                //$march_schl++;
                //echo $march_orders;
                break;
            case '4':
                $total_money_april += $school->total_money_gained;
                //$april_schl++;
                //echo $april_orders;
                break;
            case '5':
                $total_money_may += $school->total_money_gained;
                //$may_schl++;
                //echo $may_orders;
                break;
            case '6':
                $total_money_june += $school->total_money_gained;
                //$june_schl++;
                //echo $june_orders;
                break;
            case '7':
                $total_money_july += $school->total_money_gained;
                //$july_schl++;
                //echo $july_orders;
                break;
            case '8':
                $total_money_aug += $school->total_money_gained;
                //$aug_schl++;
                //echo $aug_orders."aug";
                break;
            case '9':
                $total_money_sep += $school->total_money_gained;
                //$sep_schl++;
                //echo $sep_orders;
                break;
            case '10':
                $total_money_oct += $school->total_money_gained;
                //$oct_schl++;
                //echo $oct_orders;
                break;
            case '11':
                $total_money_nov += $school->total_money_gained;
                //$nov_schl++;
                //echo $nov_orders;
                break;
            case '12':
                $total_money_dec += $school->total_money_gained;
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
    public function getExpenses() {
        $obj = new NavBarHelper();
        $user_data = $obj->getUserData();
        $site_details = $obj->siteData();
        $orders = $this->CountOrdersPerMonth();
        $total_money_gained = $this->totalMoneyGained();
        $school_donation_monthly = $this->totalSchoolDonation();
        //dd($school_donation_monthly);
        return view('admin.monthly-expenses', compact('user_data', 'site_details', 'orders', 'total_money_gained', 'school_donation_monthly'));
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
}
