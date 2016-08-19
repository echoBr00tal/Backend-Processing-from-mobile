<?php
    include_once "jquery.inc";
?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <fieldset>
        <legend>Test an Image that was uploaded</legend>
        <label>Image Path:</label>
        <input type="text" name="image_path" />
        <input type="hidden" name="action" value="image_test" />
        <input type="submit" />
    </fieldset>
</form>

<?php
    if($_POST && $_POST['action'] == "image_test"){

        $file = file_get_contents($_POST['image_path']);
        $exif = exif_read_data($_POST['image_path']);
        var_dump($exif);
        $counter = 0;
        echo '<script type="text/javascript" src="XXXXXXXXXXXXXXXXXXXXXXXXXXXX"></script>';
        echo '<img id="rotate_image_0" src="data:image/jpg;base64,'.base64_encode($file).'">';
        echo '<a href="javascript:;" id="image_'.$counter.'">Rotate Image</a>';
        echo '<input type="hidden" id="rotated_times_image_'.$counter.'" name="image['.$counter.'][rotated_times]" value="0">';
        echo '<input type="hidden" id="path_'.$counter.'" name="image['.$counter.'][path]" value="'.$image.'"><br />';
        echo '<input type="hidden" id="degrees_image_'.$counter.'" name="image['.$counter.'][degrees_counter]" value="0"><br />';
        echo '<script type="text/javascript" src="/include/jQueryRotate.2.2.js"></script>';
?>
        <script type="text/javascript">
            $(document).ready(
                function() {
                    var cur_id = "";
                    var value = 0;
                    $('a').click(function(){
                        cur_id =this.id;
                        var update = $('#rotated_times_'+cur_id).val();
                        var deg = $('#degrees_'+cur_id).val();
                        value = (parseFloat(deg) + 90);
                        $('#degrees_'+cur_id).val(value); //update degrees rotated
                        $("#rotate_"+cur_id).rotate(value); //rotate image
                        console.log(update++);
                        $('#rotated_times_'+cur_id).val(update++); //update times clicked
                    });
                    return false;
                });
        </script>
<?php
    }
?>