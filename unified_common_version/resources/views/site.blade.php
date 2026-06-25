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
                        <h4 class="card-title">Site</h4>


                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter" style="float: right;">Add New Site</button>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Site Name</th>
                                        <th>Site Url</th>
                                        {{-- <th>Action</th> --}}

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)

                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        {{-- <td>{{ $data->site_name}}</td> --}}
                                        <td><a href="{{ url('site_product', $data->id) }}" class="underline">{{ $data->site_name }}</a></td>
                                        <td>{{ $data->site_url}}</td>
                                        {{-- <td> --}}
{{-- 
                                            <button type="button" class="btn btn-primary shadow btn-xs sharp me-1" data-bs-toggle="modal" data-bs-target="#exampleModalCenter11{{ $data->id }}">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button> --}}
                                            {{-- <a href="" class="btn btn-primary shadow btn-xs sharp me-1" data-target="#exampleModalCenter1{{ $data->id }}"><i class="fas fa-pencil-alt"></i></a> --}}

                                        {{-- </td> --}}

                                    </tr>
                                    <!-- edit Modal -->
                                    <div class="modal fade" id="exampleModalCenter11{{ $data->id }}" tabindex="-1" aria-labelledby="exampleModalLabel{{ $data->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="card-body">
                                                    <h3>Edit Site</h3>
                                                    <hr>
                                                    {{-- <tr></tr> --}}
                                                    <div class="form-validation">
                                                        <form method="POST" action="{{ url('update_site') }}" id="addproductForm" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="row">
                                                                <div class="col-lg-12 mb-6">
                                                                    <div class="mb-3">
                                                                        <label class="text-label form-label">Site Name</label>
                                                                        <input type="text" name="site_name" class="form-control" value="{{ $data->site_name }}" placeholder="" required>
                                                                        <input type="hidden" name="id" class="form-control" value="{{ $data->id }}" placeholder="" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="text-label form-label">Site Url</label>
                                                                        <input type="text" name="site_url" class="form-control"value="{{ $data->site_url }}" placeholder="" required>
                                                                    </div>

                                                                    {{-- <div class="mb-3">
                                                                        <label class="text-label form-label">Site DNS</label>
                                                                        <input type="text" name="site_dns" class="form-control" placeholder="" required>
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
                <h3>Add Site</h3>
                <hr>
                <div class="form-validation">
                    <form method="POST" action="{{ url('add_site') }}" id="addproductForm" enctype="multipart/form-data">
                        @csrf
                        <?php $admin_id = session('SubAdminID'); ?>
                        <div class="row">
                            <div class="col-lg-12 mb-6">
                                {{-- <div class="mb-3">
                                    <label class="text-label form-label">Site Name</label>
                                    <input type="text" name="site_name" class="form-control" placeholder="" required>
                                </div>
                                <div class="mb-3">
                                    <label class="text-label form-label">Site Id</label>
                                    <input type="text" name="site_id" class="form-control" placeholder="" required>
                                </div> --}}
                                <div class="mb-3">
                                    <label class="text-label form-label">Site Url</label>
                                    <input type="text" name="site_url" class="form-control" placeholder="" required>
                                </div>

                                {{-- <div class="mb-3">
                                    <label class="text-label form-label">Site DNS</label>
                                    <input type="text" name="site_dns" class="form-control" placeholder="" required>
                                </div> --}}

                                <div class="mb-3">
                                    <label class="text-label form-label">Campaign Id</label>
                                    <input type="text" name="campaign_id" class="form-control" placeholder="" required>
                                </div>

                                
                                <div class="mb-3">
                                    <label class="text-label form-label">Site Key</label>
                                    <input type="text" name="site_key" class="form-control" placeholder="" required>
                                </div>

                                
                                <div class="mb-3">
                                    <label class="text-label form-label">Site Secret</label>
                                    <input type="text" name="site_secret" class="form-control" placeholder="" required>
                                </div>
                                {{-- <div class="mb-3">
                                    <label class="text-label form-label">Is Site Active?</label>
                                    <div>
                                        <input type="radio" name="is_site" value="1" required> Yes
                                        <input type="radio" name="is_site" value="0" required> No
                                    </div>
                                </div> --}}
                                <div class="col-md-12 mb-3">
                                    <label for="validationCustom02">Add Site Logo</label>
    
                                    <input type="file" name="logo" class="form-control" accept="image/*" id="new_icon" required onchange="sub_service_file_1(this);">
    
                                </div>
                                <div class="col-sm-6">
                                    <img id="sub_service_view_1" src="{{ URL::asset('public/assets/logo/upload_img.png') }}" alt="your image" class="img_details2" />
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
@include('include.footer')
@include('include.message')
