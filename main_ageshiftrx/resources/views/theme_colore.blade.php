@include('include.header')
<style>
    .underline {
        text-decoration: underline;
        color: blue;
    }
</style>
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">theme</h4>


                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter" style="float: right;">Add New theme</button>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Theme Colore</th>
                                        <th>Theme Logo</th>
                                        <th>Theme Favicon</th>
                                        {{-- <th>Action</th> --}}

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)

                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                       
                                        <td>
                                            <!-- Display color as a color box and color code -->
                                            <div style="width: 32px; height: 32px; background-color: {{ $data->theme_colore }}; border: 1px solid #ccc; display: inline-block;"></div>
                                            <span>{{ $data->theme_colore }}</span> <!-- Optional: Show the color code next to the box -->
                                        </td>
                                        
                                        <td>
                                            <a href="{{ URL::asset('public/assets/theme_logo/' . $data->theme_logo) }}" target="_blank" style="cursor: pointer;"><img src="{{ URL::asset('public/assets/theme_logo/' . $data->theme_logo) }}" width="32" height="32" class="rounded-circle mr-2" alt="Avatar">
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ URL::asset('public/assets/theme_favicon/' . $data->theme_favicon) }}" target="_blank" style="cursor: pointer;"><img src="{{ URL::asset('public/assets/theme_favicon/' . $data->theme_favicon) }}" width="32" height="32" class="rounded-circle mr-2" alt="Avatar">
                                            </a>
                                        </td>
            

                                    </tr>
                                    <!-- edit Modal -->
                                    <div class="modal fade" id="exampleModalCenter11{{ $data->theme_colore_id }}" tabindex="-1" aria-labelledby="exampleModalLabel{{ $data->theme_colore_id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="card-body">
                                                    <h3>Edit theme</h3>
                                                    <hr>
                                                    {{-- <tr></tr> --}}
                                                    <div class="form-validation">
                                                        <form method="POST" action="{{ url('update_theme') }}" id="addproductForm" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="row">
                                                                <div class="col-lg-12 mb-6">
                                                                    {{-- <div class="mb-3">
                                                                        <label class="text-label form-label">theme Name</label>
                                                                        <input type="text" name="theme_name" class="form-control" value="{{ $data->theme_name }}" placeholder="" required>
                                                                        <input type="hidden" name="id" class="form-control" value="{{ $data->id }}" placeholder="" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="text-label form-label">theme Url</label>
                                                                        <input type="text" name="theme_url" class="form-control"value="{{ $data->theme_url }}" placeholder="" required>
                                                                    </div> --}}

                                                                    {{-- <div class="mb-3">
                                                                        <label class="text-label form-label">theme DNS</label>
                                                                        <input type="text" name="theme_dns" class="form-control" placeholder="" required>
                                                                    </div> --}}
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Close</button>
                                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="exampleModalCenter">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="card-body">
                <h3>Add theme</h3>
                <hr>
                <div class="form-validation">
                    <form method="POST" action="{{ url('add_theme_colore') }}" id="addproductForm" enctype="multipart/form-data">
                        @csrf
                        <?php $admin_id = session('SubAdminID'); ?>
                        <div class="row">
                            <div class="col-lg-12 mb-6">
              
                                <div class="mb-3">
                                    <label class="text-label form-label">Theme Colore</label>
                                    <input type="color" name="theme_colore" class="form-control" value="#ffffff" onchange="updateHeaderColor(this.value)">
                                </div>
                            
                                <div class="col-md-12 mb-3">
                                    <label for="validationCustom02">Add theme Logo</label>
                                    <input type="file" name="theme_logo" class="form-control" accept="image/*" id="new_icon" required onchange="sub_service_file_1(this);">
                                </div>
                                <div class="col-sm-6">
                                    <img id="sub_service_view_1" src="{{ URL::asset('public/assets/logo/upload_img.png') }}" alt="your image" class="img_details2" />
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="validationCustom02">Add theme favicon</label>
                                    <input type="file" name="theme_favicon" class="form-control" accept="image/*" id="new_icon" required onchange="sub_service_file_2(this);">
    
                                </div>
                                <div class="col-sm-6">
                                    <img id="sub_service_view_2" src="{{ URL::asset('public/assets/logo/upload_img.png') }}" alt="your image" class="img_details2" />
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function sub_service_file_1(input) {

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#sub_service_view_1')
                    .attr('src', e.target.result)
                    .width(100)
                    .height(100);
            };

            reader.readAsDataURL(input.files[0]);
        }
    }

</script>

<script type="text/javascript">
    function sub_service_file_2(input) {

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#sub_service_view_2')
                    .attr('src', e.target.result)
                    .width(100)
                    .height(100);
            };

            reader.readAsDataURL(input.files[0]);
        }
    }

</script>

<script>
    function updateHeaderColor(color) {
        document.querySelector('header').style.backgroundColor = color;
    }
    </script>
@include('include.footer')
@include('include.message')
