<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title><?php echo __("Lütfen Bekleyin..."); ?></title>
  <style>
    form {
      visibility: hidden !important;
      display: none !important;
    }
  </style>
</head>
<body>
    <?php echo $this->fetch('content'); ?>
    <script type="text/javascript">
      var forms = document.getElementsByTagName("form");
      var debugMode = <?php echo (Configure::read('debug') > 0 ? "true" : "false"); ?>;
      if (forms.length > 0 && (debugMode && confirm("Submit form?", "Debug Mode Detected"))) forms[0].submit();
    </script>
</body>
</html>
