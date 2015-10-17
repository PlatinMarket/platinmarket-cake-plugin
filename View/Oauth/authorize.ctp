<form method="<?php echo $method; ?>" action="<?php echo $action; ?>">
  <?php foreach ($data as $key => $value) {
    if (is_string($value) || is_numeric($value))
      echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\r\n";
  } ?>
</form>
