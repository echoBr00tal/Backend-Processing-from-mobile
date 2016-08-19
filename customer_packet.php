<?php

/*
 * This is a script that creates a packet for the customer.
 *
 * All of the images that were uploaded from the mobile app,
 * all of the pdfs that were generated gets zipped up and downloaded on the
 * user's computer.
 *
 * There is a recursive function for scrubbing the same zip files.
 * This is because I want to regen the entire packet from scratch in case
 * more data was uploaded to me from the mobile app.
 */

$images = true;
$assignment_id = '225';

$assignment = new fieldreplink_vendor_assignment($assignment_id);

//create folder structure.
$directory_path = '/pdftmp/report_assignment_'.$assignment_id.'_archive';

if (!@mkdir($directory_path, 0777, true)) {
    //directory already exists... clear out the directory and start over
    delete_directory_recursive($directory_path);
    mkdir($directory_path, 0777, true);
}

//pdf goes in root of folder
//create the pdf from the questionnaire



//additional images go in sub images folder
if($images == true){
    //create the package for additional images

    $image_directory_path = $directory_path.'/images';
    @mkdir($image_directory_path, 0777, true);

    foreach($assignment->tasks as $key=>$val){
        if($val->additional_images == 'True'){
            $task = new fieldreplink_assignment_task($val->idnum);

            foreach($task->items as $item_key=>$item_val){
                if($item_val->item_type == 'image' || $item_val->item_type == 'document'){
                    //copy the item images from pdftmp into the image directory
                    $file_exploded = explode('/', $item_val->document_url);

                    if (!copy($item_val->document_url, $image_directory_path.'/'.$file_exploded[2])) {
                        mis::log('Unable to Copy image: '.$item_val->document_url.' to '.$image_directory_path, PEAR_LOG_CRIT, 'fieldreplink' );
                    }
                }
            }
        }
    }
}

//This whole folder will be zipped
zip($directory_path, '/pdftmp/report_assignment_'.$assignment_id.'_archive.zip');


/************FUNCTIONS**************/

function delete_directory_recursive($dirname) {
    if (is_dir($dirname)){
        $dir_handle = opendir($dirname);
    }
    if (!$dir_handle){
        return false;
    }

    while($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname."/".$file)){
                unlink($dirname."/".$file);
            }
            else{
                delete_directory_recursive($dirname.'/'.$file);
            }
        }
    }
    closedir($dir_handle);
    rmdir($dirname);
    return true;
}

function zip($source, $destination){
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true){
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file){
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..'))){
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true){
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true){
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true){
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

/***********************************/