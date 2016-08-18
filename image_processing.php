#!/usr/bin/php
<?php
require_once "mis_base_classes/mis.class.inc";
require_once "sbin.lib.inc";
require_once "mis_DB.inc";
require_once "fieldreplink/fieldreplink.class.inc";
require_once "fieldreplink/fieldreplink_jobs.inc";
require_once "fieldreplink/Handler.class.inc";
require_once "fieldreplink/fieldreplink_tokenkey.class.inc";
require_once "pdftmp.lib.inc";

$fieldreplink = new fieldreplink();
$jobs = new fieldreplink_jobs();
$idnum = $argv[1];

mis::log("Starting assignment_task_item_queue_handler.php fieldreplink.assignment_task_item_queue.idnum:{$idnum} fieldreplink.assignment_task_item_queue.parent_idnum:{$parent_idnum}", PEAR_LOG_INFO, 'fieldreplink_queue_processing', array('parent_idnum' => $parent_idnum, 'idnum' => $idnum));

try {
    //Sets the item status passed in to be Processing from Loaded.
    fieldreplink_item_handler::setStatus($idnum, 'PROCESSING', 'LOADED');

    $primary_query = "Select assignment_task_item_idnum, item_key from fieldreplink.assignment_task_item_queue where idnum = ?";
    $params = array($idnum);
    $row = mis_DB::pdb_central()->getRow($primary_query, $params);

    $assignment_item_id = $row['assignment_task_item_idnum'];
    $item_key = $row['item_key'];


    $assignment_id_retrieval = $fieldreplink->get_assignment_id_from_item_id($row['assignment_task_item_idnum']);
    if($assignment_id_retrieval['success']){
        $assignment_id = $assignment_id_retrieval['data']['assignment_id'];
        $status_result = $jobs->update_job_status($assignment_id, 'Incomplete');
        if($status_result['error']){
            mis::log('error updating assignment status: '.print_r($status_result,true).' assignment_id:'.print_r($assignment_id,true) , PEAR_LOG_DEBUG, 'fieldreplink_portal' );
        }
    }

    $data_query = "Select source_data from fieldreplink.assignment_task_item_queue_sources where parent_idnum = ? order by source_order";
    $data = mis_DB::pdb_central()->getAll($data_query, $params);

    $type_query = "Select item_type from fieldreplink.assignment_task_items where idnum = ?";
    if(PEAR::isError($item_types = mis_DB::pdb_central()->getAll($type_query, array($assignment_item_id)))){
        mis::log('Error retrieving item type to decrypt. Params: '.print_r(array($assignment_item_id), true), PEAR_LOG_DEBUG, 'kstest' );
        return $item_types;
    }
    $item_type = $item_types[0]['item_type'];

    $tokeys = new fieldreplink_tokenkey();

    if($item_type == 'image' || $item_type == 'document'){
        //    if so loop through and piece source data together
        $source = '';
        foreach($data as $img_key=>$img_val){
            $source .= $img_val['source_data'];
        }
        $order_num_query = "SELECT fieldreplink.vendor_assignments.order_number, fieldreplink.vendor_assignments.vendor_user_idnum FROM fieldreplink.assignment_task_items
                        JOIN fieldreplink.assignment_tasks on fieldreplink.assignment_task_items.assignment_task_idnum = fieldreplink.assignment_tasks.idnum
                        JOIN fieldreplink.vendor_assignments on fieldreplink.assignment_tasks.vendor_assignment_idnum = fieldreplink.vendor_assignments.idnum
                        where fieldreplink.assignment_task_items.idnum = ?";

        if(PEAR::isError($order = mis_DB::pdb_central()->getAll($order_num_query, array($assignment_item_id)))){
            mis::log('Error retrieving order number to decrypt. Params: '.print_r(array($assignment_item_id), true), PEAR_LOG_CRIT, 'fieldreplink_queue_processing' );
            return $data;
        }
        // run decrypt
        //need to get the vendor user id to pass to get the private keys along with the order number.
        $decrypted_values = $tokeys->decrypt_aes($order[0]['order_number'], $order[0]['vendor_user_idnum'], $item_key, $source);

        //we now have the decrypted img.  store it.
        $name = 'item_id_'.$assignment_item_id.'_'.time();
        $extension = 'jpg';

        $path = pdftmp::tempnam($name,$extension,null,$decrypted_values);
        //system("/usr/bin/convert_6.7.1 -quality 80 -density 96 -scale 768x1024 \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"", $return_value);
        //system("/usr/bin/convert -auto-orient \"{$path}\" \"/pdftmp/frl-".$name.".".$extension."\"", $return_value);

        //create another version of this that is scaled down so we can show it in the QC side of things/
        $explode_file = explode("/", $path);
        $path_scaled = "/pdftmp/scaled-frl-".$explode_file[2];

        $sizes = getimagesize($path);
        $image_width = $sizes[0];
        $image_height = $sizes[1];

        if($item_type == 'document'){
            if($image_width<$image_height){
                //rotate 90
                system("/usr/bin/convert -auto-orient -rotate \"90<\" \"{$path}\" \"{$path}\"", $return_value);
                sleep(1);
                system("/usr/bin/convert -resize 1024x1024\> -rotate \"90<\" \"{$path}\" \"{$path_scaled}\"", $return_value);
            }
            else{
                system("/usr/bin/convert -rotate \"90>\" \"{$path}\" \"{$path}\"", $return_value);
                sleep(1);
                system("/usr/bin/convert -rotate \"90>\" -resize 1024x1024\> \"{$path}\" \"{$path_scaled}\"", $return_value);
            }
        }
        else{
            system("/usr/bin/convert -resize 1024x1024\> \"{$path}\" \"{$path_scaled}\"", $return_value);
        }

        //from here, handle the error handling and document statuses in title_order.critical_docs tables
        if($path){
            $query = "Update fieldreplink.assignment_task_items SET upload_status = ?, document_status = ?, document_url = ?, item_status = ? WHERE idnum = ?";
            $params = array('Success', 'Pending', $path, 'Delivered', $assignment_item_id);
        }
        else{
            $query = "Update fieldreplink.assignment_task_items SET upload_status = 'Error' WHERE  idnum = ?";
            $params = array($assignment_item_id);
            mis::log('Error with upload path pdftmp: '.print_r($path), PEAR_LOG_CRIT, 'fileupload');
        }
        if(PEAR::isError($rs = mis_DB::pdb_central()->query($query, $params))){
            mis::log('Error Updating assignment item table. Params: '.print_r($params, true), PEAR_LOG_CRIT, 'fieldreplink_queue_processing' );
            return $rs;
        }
    }
    else{
        //    else decrypt like normal
        //get the ordernumber
        $order_num_query = "SELECT fieldreplink.vendor_assignments.order_number, fieldreplink.vendor_assignments.vendor_user_idnum FROM fieldreplink.assignment_task_items
                        JOIN fieldreplink.assignment_tasks on fieldreplink.assignment_task_items.assignment_task_idnum = fieldreplink.assignment_tasks.idnum
                        JOIN fieldreplink.vendor_assignments on fieldreplink.assignment_tasks.vendor_assignment_idnum = fieldreplink.vendor_assignments.idnum
                        where fieldreplink.assignment_task_items.idnum = ?";

        if(PEAR::isError($order = mis_DB::pdb_central()->getAll($order_num_query, array($assignment_item_id)))){
            mis::log('Error retrieving order number to decrypt. Params: '.print_r(array($assignment_item_id), true), PEAR_LOG_CRIT, 'fieldreplink_queue_processing' );
            return $order;
        }
        // run decrypt
        $decrypted_values = $tokeys->decrypt_aes($order[0]['order_number'], $order[0]['vendor_user_idnum'], $item_key, $data[0]['source_data']);

        $update_query = "Update fieldreplink.assignment_task_items SET upload_status = ?, item_response = ?, item_status = ? where idnum = ?";
        $params = array('Success', trim($decrypted_values), 'Delivered', $assignment_item_id);
        if(PEAR::isError($rs = mis_DB::pdb_central()->query($update_query, $params))){
            mis::log('Error Updating assignment item table. Params: '.print_r($params, true), PEAR_LOG_CRIT, 'fieldreplink_queue_processing' );
            return $rs;
        }
    }
    fieldreplink_item_handler::setStatus($idnum, 'PROCESSED', 'PROCESSING');

    //need to update task status to delivered once all items have been processed

    //get the task id based on item id sent over
    $task = $fieldreplink->get_task_id_from_item_id($assignment_item_id);

    if($task['success']){
        mis::log('Task is success: ', PEAR_LOG_DEBUG, 'fieldreplink_portal' );
        $task_id = $task['data']['task_id'];
        mis::log('Task ID: '.print_r($task_id, true), PEAR_LOG_DEBUG, 'fieldreplink_portal' );
        //once we have the task id, query the rest of the items based on that task to see if those statuses have been set to delivered.
        $task_items_query = "SELECT
                                  frl_ati.item_description,
                                  frl_ati.item_response,
                                  frl_ati.item_status,
                                  IF(frl_ati.item_conditional = 'No', 'NA', IF(NOT ISNULL(frl_ati_c.idnum), 'Yes', 'No')) AS conditional_met
                                FROM
                                  fieldreplink.assignment_tasks frl_at
                                INNER JOIN
                                  fieldreplink.assignment_task_items frl_ati
                                  ON
                                    frl_ati.assignment_task_idnum = frl_at.idnum
                                LEFT JOIN
                                  fieldreplink.assignment_task_items frl_ati_c
                                  ON
                                    frl_ati_c.idnum = frl_ati.conditional_item_idnum
                                    AND CAST(frl_ati_c.item_response AS CHAR) = CAST(frl_ati.conditional_item_value AS CHAR)
                                WHERE
                                  frl_at.idnum = ?";
        $params = array($task_id);
        $results = mis_DB::pdb_central()->getAll($task_items_query, $params);

        mis::log('Task Results: '.print_r($results, true), PEAR_LOG_DEBUG, 'fieldreplink_portal' );

        $total_applicable_count = 0;
        $total_delivered_count = 0;
        foreach($results as $key=>$val){
            if($val['conditional_met'] == 'NA' || $val['conditional_met'] == 'Yes'){
                $total_applicable_count++;

                if($val['item_status'] == 'Delivered' || $val['item_status'] == 'Accepted'){
                    $total_delivered_count++;
                }
            }

        }
        if($total_applicable_count == $total_delivered_count){


            //if all items in the task are uploaded, set the task status to Delivered.
            $status_result = $jobs->update_task_status($task_id, 'Delivered');
            if($status_result['error']){
                mis::log('error updating task status: '.print_r($status_result,true) , PEAR_LOG_DEBUG, 'fieldreplink_portal' );
            }
        }
        else{
            //otherwise set the task status to Incomplete
            $status_result = $jobs->update_task_status($task_id, 'Incomplete');
            if($status_result['error']){
                mis::log('error updating task status: '.print_r($status_result,true) , PEAR_LOG_DEBUG, 'fieldreplink_portal' );
            }
        }
    }

    mis::log("Assignment ID: ".$assignment_item_id." - Success", PEAR_LOG_INFO, 'fieldreplink_queue_processing');

    //exit(0);
}
catch(Exception $ex) {
    fieldreplink_item_handler::setStatus($idnum, 'CREATED');

    $error_count = fieldreplink_item_handler::incrementErrorCount($idnum);
    if($error_count >= 5) {
        fieldreplink_item_handler::setStatus($idnum, 'ERROR');
        mis::log("{$row['ordernum']}".mis::get_exception_text($ex), PEAR_LOG_CRIT, 'fieldreplink_queue_processing', '', array('parent_idnum' => $parent_idnum, 'idnum' => $idnum));
    }

    //exit(255);
}


?>