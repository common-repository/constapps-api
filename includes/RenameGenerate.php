<?php

add_filter( 'intermediate_image_sizes_advanced', 'bt_intermediate_image_sizes_advanced' );
add_filter( 'wp_generate_attachment_metadata', 'bt_generate_attachment_metadata', 10, 2 );

function bt_add_image_size( $name, $width = 0, $height = 0, $crop = false ) {
	global $_wp_additional_image_sizes;
	$_wp_additional_image_sizes[$name] = array( 'width' => absint( $width ), 'height' => absint( $height ), 'crop' => $crop );
}

function bt_intermediate_image_sizes_advanced() {
	return array();
}

function bt_generate_attachment_metadata( $metadata, $attachment_id ) {
    $attachment = get_post( $attachment_id );
    $uploadPath = wp_upload_dir();
    $file = path_join($uploadPath['basedir'], $metadata['file']);
	if ( !preg_match('!^image/!', get_post_mime_type( $attachment )) || !file_is_displayable_image( $file ) ) return $metadata;
	global $_wp_additional_image_sizes;
    foreach ( get_intermediate_image_sizes() as $s ) {
        $sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => FALSE );
        if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
            $sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] ); // For theme-added sizes
        else
            $sizes[$s]['width'] = get_option( "{$s}_size_w" ); // For default sizes set in options
        if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
            $sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] ); // For theme-added sizes
        else
            $sizes[$s]['height'] = get_option( "{$s}_size_h" ); // For default sizes set in options
        if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
            $sizes[$s]['crop'] = $_wp_additional_image_sizes[$s]['crop'];
        else
            $sizes[$s]['crop'] = get_option( "{$s}_crop" );
    }
    foreach ( $sizes as $size => $size_data ) {
        $resized = bt_image_make_intermediate_size( $file, $size_data['width'], $size_data['height'], $size_data['crop'], $size );
        if ( $resized )
            $metadata['sizes'][$size] = $resized;
    }
    return $metadata;
}

function bt_image_make_intermediate_size( $file, $width, $height, $crop = false, $size ) {
	if ( $width || $height ) {
		switch($size) {
			case 'thumbnail':
				$suffix = 'small';
				break;
			case 'medium':
				$suffix = 'medium';
				break;
			case 'large':
				$suffix = 'large';
				break;
			default:
				$suffix = null;
				break;
		}
		$resized_file = bt_image_resize( $file, $width, $height, $crop, $suffix, null, 90 );
		if ( !is_wp_error( $resized_file ) && $resized_file && $info = getimagesize( $resized_file ) ) {
			$resized_file = apply_filters('image_make_intermediate_size', $resized_file);
			return array(
				'file' => wp_basename( $resized_file ),
				'width' => $info[0],
				'height' => $info[1],
			);
		}
	}
	return false;
}

function bt_image_resize_dimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop = false) {
	if ($orig_w <= 0 || $orig_h <= 0)
		return false;
	if ($dest_w <= 0 && $dest_h <= 0)
		return false;
	if ( $crop ) {
		$aspect_ratio = $orig_w / $orig_h;
		$new_w = min($dest_w, $orig_w);
		$new_h = min($dest_h, $orig_h);

		if ( !$new_w ) {
			$new_w = intval($new_h * $aspect_ratio);
		}

		if ( !$new_h ) {
			$new_h = intval($new_w / $aspect_ratio);
		}

		$size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

		$crop_w = round($new_w / $size_ratio);
		$crop_h = round($new_h / $size_ratio);

        if ( !is_array( $crop ) || count( $crop ) != 2 ) {
			$crop = apply_filters( 'image_resize_crop_default', array( 'center', 'center' ) );
		}

		switch ( $crop[0] ) {
			case 'left': $s_x = 0; break;
			case 'right': $s_x = $orig_w - $crop_w; break;
			default: $s_x = floor( ( $orig_w - $crop_w ) / 2 );
		}

		switch ( $crop[1] ) {
			case 'top': $s_y = 0; break;
			case 'bottom': $s_y = $orig_h - $crop_h; break;
			default: $s_y = floor( ( $orig_h - $crop_h ) / 2 );
		}
	} else {
		$crop_w = $orig_w;
		$crop_h = $orig_h;

		$s_x = 0;
		$s_y = 0;

		list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
	}
	return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
}

function bt_image_resize( $file, $max_w, $max_h, $crop = false, $suffix = null, $dest_path = null, $jpeg_quality = 90 ) {

	$image = wp_load_image( $file );
	if ( !is_resource( $image ) )
		return new WP_Error( 'error_loading_image', $image, $file );

	$size = @getimagesize( $file );
	if ( !$size )
		return new WP_Error('invalid_image', __('Could not read image size'), $file);
	list($orig_w, $orig_h, $orig_type) = $size;

	$rotate = false;
	if ( is_callable( 'exif_read_data' ) && in_array( $orig_type, apply_filters( 'wp_read_image_metadata_types', array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ) ) ) ) {
		$exif = @exif_read_data( $file, null, true );
		if ( $exif && isset( $exif['IFD0'] ) && is_array( $exif['IFD0'] ) && isset( $exif['IFD0']['Orientation'] ) ) {
			if ( 6 == $exif['IFD0']['Orientation'] )
				$rotate = 90;
			elseif ( 8 == $exif['IFD0']['Orientation'] )
				$rotate = 270;
		}
	}

	if ( $rotate )
		list($max_h,$max_w) = array($max_w,$max_h);

	$dims = bt_image_resize_dimensions($orig_w, $orig_h, $max_w, $max_h, $crop);
	if ( !$dims )
		return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions') );
	list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;

	$newimage = wp_imagecreatetruecolor( $dst_w, $dst_h );

	if ( $rotate )
		list($src_y,$src_x) = array($src_x,$src_y);

	imagecopyresampled( $newimage, $image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

	if ( IMAGETYPE_PNG == $orig_type && function_exists('imageistruecolor') && !imageistruecolor( $image ) )
		imagetruecolortopalette( $newimage, false, imagecolorstotal( $image ) );

	imagedestroy( $image );

	if ( !$suffix ) {
		if ( $rotate )
			$suffix = "{$dst_h}x{$dst_w}";
		else
			$suffix = "{$dst_w}x{$dst_h}";
	}

	$info = pathinfo($file);
	$dir = $info['dirname'];
	$ext = $info['extension'];
	$name = wp_basename($file, ".$ext");

	if ( !is_null($dest_path) and $_dest_path = realpath($dest_path) )
		$dir = $_dest_path;
	$destfilename = "{$dir}/{$name}-{$suffix}.{$ext}";

	if ( IMAGETYPE_GIF == $orig_type ) {
		if ( !imagegif( $newimage, $destfilename ) )
			return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
	} elseif ( IMAGETYPE_PNG == $orig_type ) {
		if ( !imagepng( $newimage, $destfilename ) )
			return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
	} else {
		if ( $rotate ) {
			$newimage = _rotate_image_resource( $newimage, 360 - $rotate );
		}
		$destfilename = "{$dir}/{$name}-{$suffix}.jpg";
		$return = imagejpeg( $newimage, $destfilename, apply_filters( 'jpeg_quality', $jpeg_quality, 'image_resize' ) );
		if ( !$return )
			return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
	}

	imagedestroy( $newimage );

	$stat = stat( dirname( $destfilename ));
	$perms = $stat['mode'] & 0000666;
	@ chmod( $destfilename, $perms );

	return $destfilename;
}
