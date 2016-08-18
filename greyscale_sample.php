<?php

/*******
 * Another mobile data processing sample.
 * In this case, we wanted to convert the images that were taken from the mobile app
 * to gray scale as well as sharpen up the edges a bit.
 *
 * Converting to gray helps in size storage and speed of showing them on the page
 * in the web portal, users login to, to view the orders.
 *
********/

if($results[0]['item_type'] == "document"){
    $pdf->setFont("Helvetica", "25", "Bold" ); // Set the font for the note
    $pdf->setNote("Document: ".$results[0]['item_description']);	// Set the note content
    $pdf->newPage();

    //run some imagemagick to scale down image to make smaller
    $file_exploded = explode('/', $results[0]['document_url']);
    $name_exploded = explode('.',$file_exploded[2]);
    $optimized_filename = $name_exploded[0].'.'.$name_exploded[1].'.jpg';
    $new_file_name ='esigning-optimizedjpg-'.$optimized_filename;

    //system("/usr/bin/convert -quality 80 -density 96 -scale 768x1024 \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"", $return_value);
    system("/usr/bin/convert -colorspace gray -type grayscale -contrast-stretch 0 -sharpen 0x1 \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"");
    //system("/usr/bin/convert -colorspace gray -type grayscale -contrast-stretch 0 \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"");

    //*** Attempts ***//
    //system("/usr/bin/convert -fill white +opaque black \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"", $return_value);
    //system("/usr/bin/convert ( +clone -matte -transparent black -fill white -colorize 100% ) -composite \"{$results[0]['document_url']}\" \"/pdftmp/{$new_file_name}\"", $return_value);
    //system("/usr/bin/convert \"{$results[0]['document_url']}\" -bordercolor white -border 1x1 -alpha set -channel RGBA -fuzz 20% -fill none -floodfill +0+0 \"/pdftmp/{$new_file_name}\"");
    //system("/usr/bin/convert \"/pdftmp/{$new_file_name}\" -background black -flatten \"/pdftmp/{$new_file_name}\"");
    ////////////////////

    $pdf->setImg('jpeg', '/pdftmp/'.$new_file_name);
    //$pdf->setImg('jpeg', $results[0]['document_url']);
    $pdf->getImgUp('15', '0', 'boxsize={550 900} fitmethod=meet position={left top}');
}