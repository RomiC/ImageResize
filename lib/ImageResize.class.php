<?php

/**
 * Class for images resizing
 * Supported fromats are: jpeg, gif, png
 * @author Roman Charugin <roman.charugin@gmail.com>
 */
class ImageResize {
	/**
	 * image resource
	 * @var resource
	 */
	private $Image;
	/**
	 * Name of a input file
	 * @var string
	 */
	private $ImageName;
	/**
	 * Name of a input file
	 * @var string
	 */
	private $ImageType;
	/**
	 * Name of a input file
	 * @var string
	 */
	private $ImageWidth;
	/**
	 * Name of a input file
	 * @var string
	 */
	private $ImageHeight;

	/**
	 * Resize side (none, width, height, big, small):
	 * - none - new image's width and height will be exactly $newWidth and $newHeight
	 * - width - as the sourced side will be used width, height will change proportionally
	 * - height - as the sourced side will be used height, width will change proportionally
	 * - big - as the sourced side will be used a bigger side, the other one will change proportionally
	 * - small - as the sourced side will be used a smaller side, the other one will change proportionally
	 * - restricted - the biggest side will be exactly $newWidth or $newHeight accordingly, the other one will change proportionally
	 * @var string
	 */
	public $BaseSide = 'big';

	/**
	 * If it's true, then source image wouldn't be resized if its width or height are smaller than $newWidth or $newHeight
	 * @var boolean
	 */
	public $ReduceOnly = false;

	/**
	 * Constructor
	 * @param string|resource $I String - path to image-file or the key in the $_FILES array, or image-resourse.
	 */
	public function ImageResize($I) {
		if (@is_string($I)) {
			$ImageFile = '';

		if (@preg_match('/([^\/]+).(jpg|jpeg|gif|png){1,1}$/i', $I, $tmp)) {
			$ImageFile = $I;
			$this->ImageName = $tmp[1];
			$this->ImageType = 'image/'. strtolower($tmp[2]);
		} elseif (@is_uploaded_file($_FILES[$I]['tmp_name'])) {
			$ImageFile = $_FILES[$I]['tmp_name'];
			$this->ImageName = $_FILES[$I]['name'];
			$this->ImageType = $_FILES[$I]['type'];
		} else {
			throw new ImageResizeException("Wrong input parameter '{$I}'!");
		}

		switch ($this->ImageType) {
			case 'image/pjpeg':
			case 'image/jpeg':
			case 'image/jpg':
				if (!$this->Image = @imagecreatefromjpeg($ImageFile))
					throw new ImageResizeException("Impossible to create image from jpeg-file '{$ImageFile}'!");
				break;

			case 'image/gif':
				if (!$this->Image = @imagecreatefromgif($ImageFile))
					throw new ImageResizeException("Impossible to create image from gif-file '{$ImageFile}'!");
				break;

			case 'image/x-png':
			case 'image/png':
				if (!$this->Image = @imagecreatefrompng($ImageFile))
					throw new ImageResizeException("Impossible to create image from png-file '{$ImageFile}'!");
				break;

			default:
				throw new ImageResizeException("Unsupported file format '{$this->ImageType}'!");
		}

		// Delete temporary uploaded file, if it's exists
		if (@is_uploaded_file($I))
			unlink($_FILES[$I]['tmp_name']);
		} elseif (@is_resource($I)) {
			$this->Image = $I;
			$this->ImageName = $this->ImageType = '';
		} else {
			throw new ImageResizeException('Unsupported type of the main parameter!');
		}

		$this->ImageWidth = imagesx($this->Image);
		$this->ImageHeight = imagesy($this->Image);
	}

	/**
	 * Resize method
	 * @param int $W New images' width
	 * @param int $H New images' height
	 * @return resource Resized image resource
	 */
	public function Resize($W, $H) {
		$newWidth = $W;
		$newHeight = $H;

		// If source image is smaller than new one and ReduceOnly option is true
		if ($this->ReduceOnly && ($newWidth > $this->ImageWidth) && ($newHeight > $this->ImageHeight))
			return $this->Image;	// then return source image

		switch ($this->BaseSide) {
			case 'none':
				break;

			case 'width':
				$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				break;

			case 'height':
				$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				break;

			case 'big':
				if ($this->ImageWidth > $this->ImageHeight)
					$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				elseif ($this->ImageWidth < $this->ImageHeight)
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				elseif ($newWidth > $newHeight)
					$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				else
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				break;

			case 'small':
				if ($this->ImageWidth < $this->ImageHeight)
					$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				elseif ($this->ImageWidth > $this->ImageHeight)
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				elseif ($newWidth < $newHeight)
					$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				else
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				break;

			case 'restricted': 
				if ($this->ImageWidth < $this->ImageHeight)
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				elseif ($this->ImageWidth > $this->ImageHeight)
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				elseif ($newWidth < $newHeight)
					$newHeight = (int)($newWidth /$this->ImageWidth * $this->ImageHeight);
				else
					$newWidth = (int)($newHeight /$this->ImageHeight * $this->ImageWidth);
				break;
		}

		if (!($img = @imagecreatetruecolor($newWidth, $newHeight)))
			throw  new ImageResizeException('Impossible to create new image!');

		if (!@imagecopyresampled($img, $this->Image, 0, 0, 0, 0, $newWidth, $newHeight, $this->ImageWidth, $this->ImageHeight))
			throw  new ImageResizeException('Impossible to resize image!');

		return $img;
	}

	/**
	 * Resize image and save it to the file
	 * @param int $W New images' width
	 * @param int $H New images' height
	 * @param string $F Name of output file (may use '%base%' as template to insert the name of the source image), file extension will be added automaticaly if not specified
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param $F Combination of PNG_FILTER_XXX constans
	 */
	public function ResizeAndSave($W, $H, $F, $T, $Q = 0, $FL = PNG_NO_FILTER) {
		$this->Save($this->Resize($W, $H), $F, $T, $Q, $FL);
	}

	/**
	 * Resize image and send it to the standart output with specific header
	 * @param int $W New images' width
	 * @param int $H New images' height
	 * @param string $F Name of output file (may use '%base%' as template to insert the name of the source image), file extension will be added automaticaly if not specified
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param int $F Combination of PNG_FILTER_XXX constans
	 */
	public function ResizeAndOutput($W, $H, $T, $Q = 0, $FL = PNG_NO_FILTER) {
		$this->Output($this->Resize($W, $H), $T, $Q, $FL);
	}

	/**
	 * Create the thumbnail of the source image
	 * @param int $TW Thumb width (should be smaller than source image width)
	 * @param int $TH Thumb height (should be smaller than source image height)
	 * @param string|array $BC Thumb background color:
	 * - string - hex represantion of color, e.g. '#AABBCC' or '#ABC'
	 * - int[3] - each element is an integer represation of a color component (Red, Green, Blue), e.g. array(255, 128, 10)
	 * @param string $HA Horizontal align: 'left', 'center', 'right'
	 * @param string $VA Vertical align: 'top', 'center', 'bottom'
	 * @return resource Thumbs' resource
	 */
	public function MakeThumb($TW, $TH, $BC, $HA = 'center', $VA = 'center') {
		$newX = $newY = $newWidth = $newHeight = 0;

		if ($this->ImageWidth > $this->ImageHeight) {
			$newWidth = $TW;
			$newHeight = $newWidth * $this->ImageHeight / $this->ImageWidth;
		} elseif ($this->ImageWidth < $this->ImageHeight) {
			$newHeight = $TH;
			$newWidth = $newHeight * $this->ImageWidth / $this->ImageHeight;
		} else {
			$newWidth = $TW;
			$newHeight = $TH;
		}

		if ($HA == 'center')
			$newX = (int)(($TW - $newWidth) / 2);
		elseif ($HA == 'right')
			$newX = ($TW - $newWidth);

		if ($VA == 'center')
			$newY = (int)(($TH - $newHeight) / 2);
		elseif ($VA == 'bottom')
			$newY = ($TH - $newHeight);

		if (!($img = @imagecreatetruecolor($TW, $TH)))
			throw  new ImageResizeException('Impossible to create new image!');

		$color = $this->getColor($BC);
		if (!@imagefill($img, 0, 0, $color))
			throw  new ImageResizeException('Can\'t fill the image!');

		if (!@imagecopyresampled($img, $this->Image, $newX, $newY, 0, 0, $newWidth, $newHeight, $this->ImageWidth, $this->ImageHeight))
			throw  new ImageResizeException('Impossible to make the thumbnail!');

		return $img;
	}

	/**
	 * Create thumbnail and save it to the file
	 * @param int $TW Thumb width (should be smaller than source image width)
	 * @param int $TH Thumb height (should be smaller than source image height)
	 * @param string|array $BC Thumb background color:
	 * - string - hex represantion of color, e.g. '#AABBCC' or '#ABC'
	 * - int[3] - each element is an integer represation of a color component (Red, Green, Blue), e.g. array(255, 128, 10)
	 * @param string $HA Horizontal align: 'left', 'center', 'right'
	 * @param string $VA Vertical align: 'top', 'center', 'bottom'
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param int $F Combination of PNG_FILTER_XXX constans
	 */
	function MakeThumbAndSave($TW, $TH, $BC, $HA = 'center', $VA = 'center', $F, $T, $Q = 0, $FL = PNG_NO_FILTER) {
		$this->Save($this->MakeThumb($TW, $TH, $BC, $HA, $VA), $F, $T, $Q, $FL);
	}

	/**
	 * Create thumbnail and send it to the standart output
	 * @param int $TW Thumb width (should be smaller than source image width)
	 * @param int $TH Thumb height (should be smaller than source image height)
	 * @param string|array $BC Thumb background color:
	 * - string - hex represantion of color, e.g. '#AABBCC' or '#ABC'
	 * - int[3] - each element is an integer represation of a color component (Red, Green, Blue), e.g. array(255, 128, 10)
	 * @param string $HA Horizontal align: 'left', 'center', 'right'
	 * @param string $VA Vertical align: 'top', 'center', 'bottom'
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param int $F Combination of PNG_FILTER_XXX constans
	 */
	function MakeThumbAndOutput($TW, $TH, $BC, $HA = 'center', $VA = 'center', $T, $Q = 0, $FL = PNG_NO_FILTER) {
		$this->Output($this->MakeThumb($TW, $TH, $BC, $HA, $VA), $T, $Q, $FL);
	}

	/**
	 * Create color for the current image
	 * @param string|array $C String - hex represantion of color, e.g. '#AABBCC' or '#ABC', array - each element is an integer represation of the color component (Red, Green, Blue), e.g. array(255, 128, 10)
	 * @return color identifier
	 */
	public function getColor($C) {
		$red = $green = $blue = 0;

		if (is_string($C)) {
			if (preg_match('/^#([0-9a-fA-F]{2,2})([0-9a-fA-F]{2,2})([0-9a-fA-F]{2,2})$/', $C, $tmp)) {
				$red = hexdec($tmp[1]);
				$green = hexdec($tmp[2]);
				$blue = hexdec($tmp[3]);
			} elseif (preg_match('/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $C, $tmp)) {
				$red = hexdec("{$tmp[1]}{$tmp[1]}");
				$green = hexdec("{$tmp[2]}{$tmp[2]}");
				$blue = hexdec("{$tmp[3]}{$tmp[3]}");
			} else {
				throw new ImageResizeException('Wrong color format!');
			}
		} elseif (is_array($C) && count($C) == 3) {
			$red = $C[0];
			$green = $C[1];
			$blue = $C[2];
		} else {
			throw new ImageResizeException('Wrong color format!');
		}

		return imagecolorallocate($this->Image, $red, $green, $blue);
	}

	/**
	 * Save image to the file
	 * @param resource $I Resource of the image to be saved
	 * @param string $F Name of the output file (may use '%base%' as template to insert the name of the source image), file extension will be added automaticaly if not specified
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param int $FL Combination of PNG_FILTER_XXX constans
	 */
	function Save($I, $F, $T, $Q = 0, $FL = PNG_NO_FILTER) {
		$filename = str_replace('%base%', $this->ImageName, $F);
		$extensionSpecified = preg_match('/.(jpg|jpeg|png|gif)$/', $F);

		switch ($T) {
			case 'jpg':
			case 'jpeg':
				$filename .= ((!$extensionSpecified) ? '.jpg' : '');
				if (!@imagejpeg($I, $filename, $Q))
					throw new ImageResizeException("Impossible to save resized image as {$filename}!");
				break;

			case 'gif':
				$filename .= ((!$extensionSpecified) ? '.gif' : '');
				if (!@imagegif($I, $filename))
					throw new ImageResizeException("Impossible to save resized image as {$filename}!");
				break;

			case 'png':
				$filename .= ((!$extensionSpecified) ? '.png' : '');
				if (!@imagepng($I, $filename, $Q, $FL))
					throw new ImageResizeException("Impossible to save resized image as {$filename}!");
				break;

			default:
				throw new ImageResizeException('Unsupported output format!');
		}
	}

	/**
	 * Send image to the standart output with specific header without saving to the file.
	 * @param resource $I Resource of the image to be sent
	 * @param string $T Type of output image: 'jpeg', 'png' or 'gif'
	 * @param int $Q Quality of the ouput image (only for jpeg and png-images, not for gif):
	 * - from 0 (worst quality) to 100 (best quality) for jpeg-images
	 * - from 0 (no compression) to 9 for png-images
	 * @param int $FL Combination of PNG_FILTER_XXX constans
	 */
	function Output($I, $T, $Q = 0, $FL = PNG_NO_FILTER) {
		switch ($T) {
			case 'jpg':
			case 'jpeg':
				header('Content-type: image/jpeg');
				if (!@imagejpeg($I, NULL, $Q))
					throw new ImageResizeException('Impossible to save resized image as jpeg!');
				break;

			case 'gif':
				header('Content-type: image/gif');
				if (!@imagegif($I, NULL))
					throw new ImageResizeException('Impossible to save resized image as gif!');
				break;

			case 'png':
				header('Content-type: image/png');
				if (!@imagepng($I, NULL, $Q, $FL))
					throw new ImageResizeException('Impossible to save resized image as png!');
				break;

			default:
				throw new ImageResizeException('Unsupported output format!');
		}
	}
}

/*
 * Class represent the extended exception for the class above
 */
class ImageResizeException extends Exception {
	/**
	 * Default output format
	 * @var string
	 */
	public $OutputFormat = '<!-- %S% -->';

	/**
	 * Format output of the exception
	 * @param string $F Format of the output string, may use %S% for the template, to paste the original message
	 * @return string Format string
	 */
	public function Format($F = '') {
		if (strlen($F) > 0)
			return str_replace('%S%', $this->getMessage(), $F);
		else
			return str_replace('%S%', $this->getMessage(), $this->OuputFormat);
	}
}

?>