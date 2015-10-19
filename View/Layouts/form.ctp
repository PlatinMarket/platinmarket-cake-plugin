<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title><?php echo __("Lütfen Bekleyin..."); ?></title>
	<?php echo $this->element('settings'); ?>
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
			(function(){
      	var forms = document.getElementsByTagName("form");
				if (forms.length == 0) return;
				if (debug === 0) return forms[0].submit();
	      if (confirm("Submit form?", "Debug Mode Detected")) forms[0].submit();
			})();
    </script>
</body>
</html>
