<script>
	$(document).ready(function(){
		function myFunc() {
			if (document.getElementsByClassName('st-label').length > 0) {
				viewstr = $('.st-label').text();
				multiplier = 5;
				viewint = parseInt(viewstr) * multiplier;
				$('.st-label').text(viewint.toString())
			} else {
				setTimeout(myFunc, 500);
			}
		}
		myFunc();
	});
</script>
