<?php

namespace App\Classes;
use Storage;
use Mail;
use File;
use DB;

class Helper
{
    public function getExtension($parameter)
    {
        return $parameter->extension();
    }

    public function isFileImage($parameter)
    {
        if($parameter == "png" || $parameter == "jpg" || $parameter == "jpeg" || $parameter == "avif")
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    public function isFileVideo($parameter)
    {
        if($parameter == "mkv" || $parameter == "mp4" || $parameter == "wmv" || $parameter == "avi" || $parameter == "mov" || $parameter == "flv" || $parameter == "bin")
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    public function saveProfile($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/admin_profile'),$name);

        return $name;
    }
    public function euro_slider($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/euro_Slider'),$name);

        return $name;
    }

    public function Packages($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/Packages'),$name);

        return $name;
    }

    public function AddOn($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/AddOn'),$name);

        return $name;
    }
    public function Slider($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/Slider'),$name);

        return $name;
    }
    public function captain_icon($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/captain_icon'),$name);

        return $name;
    }
    public function saveReturnAssetIcon($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/saveReturnAssetIcon'),$name);

        return $name;
    }
    public function saveCustomerReview($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/customer_review'),$name);

        return $name;
    }

     public function saveMainService($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/MainService'),$name);

        return $name;
    }


         public function saveSubService($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/SubService'),$name);

        return $name;
    }

    public function savePartnersPhoto($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/savePartnersPhoto'),$name);

        return $name;
    }

    public function police_clearance_certificate($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/police_clearance_certificate'),$name);

        return $name;
    }
    public function id_proof($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/id_proof'),$name);

        return $name;
    }
    public function driving_license($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/driving_license'),$name);

        return $name;
    }



    public static function savesub_admin($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/sub_admin_profile'),$name);

        return $name;
    }

    public function saveThumbnail($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/video_thumbnail'),$name);

        return $name;
    }

    public function saveVideo($parameter)
    {
        if($parameter->extension() == "bin")
        {
            $name = rand(11111111,99999999).time().".mp4";
        }

        $name = rand(11111111,99999999).time().".mp4";
        $parameter->move(public_path('assets/videos'),$name);

        return $name;
    }


    public static function savesite_logo($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/site_logo'),$name);

        return $name;
    }

    public static function product_img($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/product_img'),$name);

        return $name;
    }

    public static function savetheme_logo($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/theme_logo'),$name);

        return $name;
    }

    public static function savetheme_favicon($parameter)
    {
        $name = rand(11111111,99999999).time().".".$parameter->extension();
        $parameter->move(public_path('assets/theme_favicon'),$name);

        return $name;
    }
}