<?php

namespace App\Classes;

use Illuminate\Http\Request;
use DB;
use Mail;
use File;
use Storage;

class CRUD 
{

    public $success = "success";
    public $error   = "error";

    //for insertion data
    public function InsertData($tableName,$insertionData)
    {
        if(DB::table($tableName)->insert([$insertionData]))
        {
            return "success";
        }
        else
        {
            return "error";
        }
    }    

    //to update data
    public function UpdationData($tableName,$conditionParam,$conditionValue,$updateData)
    {
        if(DB::table($tableName)->where($conditionParam,$conditionValue)->update([$updateData]))
        {
            return "success";
        }
        else
        {
            return "error";
        }
    }

    //to delete data
    public function DeleteData($tableName,$conditionParam,$conditionValue)
    {
        if(DB::table($tableName)->where($conditionParam,$conditionValue)->delete())
        {
            return "success";
        }
        else
        {
            return "error";
        }
    }

    //file Checker
    public function fileFormatChecker($file)
    {
        if($request->file($file)->extension() == "jpg" || $request->file($file)->extension() == "jpeg" || $request->file($file)->extension() == "png" || $request->file($file)->extension() == "webp" || $request->file($file)->extension() == "gif")
        {
            return "image";
        }
        elseif($request->file($file)->extension() == "mp4" || $request->file($file)->extension() == "mov" || $request->file($file)->extension() == "wmv" || $request->file($file)->extension() == "avi" || $request->file($file)->extension() == "avchd" || $request->file($file)->extension() == "flv" || $request->file($file)->extension() == "mkv")
        {
            return "video";
        }
        elseif($request->file($file)->extension() == "pdf")
        {
            return "pdf";
        }
        elseif($request->file($file)->extension() == "html" || $request->file($file)->extension() == "txt" || $request->file($file)->extension() == "js" || $request->file($file)->extension() == "css" || $request->file($file)->extension() == "py" || $request->file($file)->extension() == "php" || $request->file($file)->extension() == "phtml" || $request->file($file)->extension() == "c" || $request->file($file)->extension() == "cs" || $request->file($file)->extension() == "cpp" || $request->file($file)->extension() == "ppt" || $request->file($file)->extension() == "doc" || $request->file($file)->extension() == "docx" || $request->file($file)->extension() == "xlsx" || $request->file($file)->extension() == "xlsm" || $request->file($file)->extension() == "blade" || $request->file($file)->extension() == "java" || $request->file($file)->extension() == "xml" || $request->file($file)->extension() == "json")
        {
            return "document";
        }
        else
        {
            return "Unknown Format";
        }
    }

    //user is exist or not checker
    public function isUserExist($tableName,$userParam,$userValue)
    {
        if(DB::table($tableName)->where($userParam,$userValue)->count() == 1)
        {
            return "success";
        }
        else
        {
            return "error";
        }
    }

    //single file remover
    public function deleteSingleFile($filePath,$fileName)
    {
        if(File::exists($filePath.$fileName))
        {
            unlink($filePath.$fileName);

            return "Deleted SuccessFully";
        }
        else
        {
            return "No File Exist";
        }
    }

    //multiple file delete
    public function deleteMultipleFile($filePath,$fileName)
    {
        foreach($fileName as $fileName)
        {
            if(File::exists($filePath.$fileName))
            {
                unlink($filePath.$fileName);

                return "Deleted SuccessFully";
            }
            else
            {
                return "No File Exist";
            }
        }
    }

    


}
