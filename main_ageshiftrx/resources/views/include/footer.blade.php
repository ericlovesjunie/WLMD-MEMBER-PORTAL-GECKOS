
        <!--**********************************
            Footer start
        ***********************************-->
        <div class="footer">
            <div class="copyright">
                <p>Copyright © Designed &amp; Developed by <a href="https://binarygeckos.com/" target="_blank">binarygeckos</a> 2024</p>
            </div>
        </div>
        <!--**********************************
            Footer end
        ***********************************-->

		<!--**********************************
           Support ticket button start
        ***********************************-->
		
        <!--**********************************
           Support ticket button end
        ***********************************-->


	</div>
    <!--**********************************
        Main wrapper end
    ***********************************-->

    <!--**********************************
        Scripts
    ***********************************-->
    <!-- Required vendors -->
    <script src="{{ URL::asset('public/assets/vendor/global/global.min.js') }}" ></script>
	<script src="{{ URL::asset('public/assets/vendor/chart.js/Chart.bundle.min.js') }}" ></script>
    <script src="{{ URL::asset('public/assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ URL::asset('public/assets/js/plugins-init/datatables.init.js') }}"></script>
	<script src="{{ URL::asset('public/assets/vendor/jquery-nice-select/js/jquery.nice-select.min.js') }}" ></script>
	
	<!-- Apex Chart -->
	<script src="{{ URL::asset('public/assets/vendor/apexchart/apexchart.js') }}" ></script>
	
	<script src="{{ URL::asset('public/assets/vendor/chart.js/Chart.bundle.min.js') }}" ></script>
	
	<!-- Chart piety plugin files -->
    <script src="{{ URL::asset('public/assets/vendor/peity/jquery.peity.min.js') }}" ></script>
	<!-- Dashboard 1 -->
	<script src="{{ URL::asset('public/assets/js/dashboard/dashboard-1.js') }}" ></script>
	
	<script src="{{ URL::asset('public/assets/vendor/owl-carousel/owl.carousel.js') }}" ></script>
	
    <script src="{{ URL::asset('public/assets/js/custom.min.js') }}" ></script>
	<script src="{{ URL::asset('public/assets/js/dlabnav-init.js') }}" ></script>
	<script src="{{ URL::asset('public/assets/js/demo.js') }}" ></script>
    {{-- <script src="{{ URL::asset('public/assets/js/styleSwitcher.js') }}" ></script> --}}

    
	<script>
		function cardsCenter()
		{
			
			/*  testimonial one function by = owl.carousel.js */
			
	
			
			jQuery('.card-slider').owlCarousel({
				loop:true,
				margin:10,
				nav:false,
				//center:true,
				slideSpeed: 3000,
				paginationSpeed: 3000,
				dots: false,
				navText: ['', ''],
				responsive:{
					0:{
						items:1
					},
					576:{
						items:2
					},	
					800:{
						items:2
					},			
					991:{
						items:2
					},
					1200:{
						items:3
					},
					1600:{
						items:4
					}
				}
			})
		}
		
		jQuery(window).on('load',function(){
			setTimeout(function(){
				cardsCenter();
			}, 1000); 
		});
		
	</script>

</body>
</html>