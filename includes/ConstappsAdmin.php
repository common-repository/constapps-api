
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Hello, world!</title>
  </head>
  <body>


<div class="wrap">
	<h1>Constapps API Settings</h1>

  <br>

  <div class="thanks">
  <p style="font-size: 16px;">Thank you for choosing ConstApps products.</p>
	</div>
</div>

  <form action="" enctype="multipart/form-data" method="post">
  
    <div class="form-group" style="margin-top:30px">
        <input id="fileToUpload" accept=".json" name="fileToUpload" type="file" class="form-control-file">
    </div>
    
    <p style="font-size: 14px; color: #1B9D0D; margin-top:10px">
    <?php
    if (isset($_POST['but_submit'])) {     
      wp_upload_bits($_FILES['fileToUpload']['name'], null, file_get_contents($_FILES['fileToUpload']['tmp_name'])); 
      $uploads_dir = dirname( __FILE__ );
      $source      = sanitize_text_field($_FILES['fileToUpload']['tmp_name']);
      $destination = trailingslashit( $uploads_dir ) . sanitize_text_field($_FILES['fileToUpload']['name']);
      move_uploaded_file($source, $destination);
      echo "The caching is active.";
    }else{
      if (file_exists($uploads_dir = dirname( __FILE__ )."/config.json")) {
        echo "The caching is active.";
      }
    }
    ?>
    </p>

    <button type="submit" class="btn btn-primary" name='but_submit'>Save</button>
    </form>
  </body>
</html>