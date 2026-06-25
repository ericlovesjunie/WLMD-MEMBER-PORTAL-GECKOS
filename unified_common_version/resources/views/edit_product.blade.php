@include('include.header')
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Edit Product</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-validation">
                            <form method="POST" action="{{ url('update_product', $table_data_pro->product_id) }}" id="addproductForm" enctype="multipart/form-data">
                                @csrf
                                <?php $admin_id = session('SubAdminID'); ?>
                                <div class="row">
                                    <div class="col-lg-12 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product Name</label>
                                            <input type="text" name="product_name" class="form-control" value="{{ $table_data_pro->product_name }}" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Form URL</label>
                                            <input type="text" name="form_url" id="form_url" value="{{ $table_data_pro->form_url }}" class="form-control" placeholder="">
                                            <input type="hidden" class="form-control" name="form_id" id="form_id">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product Price</label>
                                            <input type="number" class="form-control" value="{{ $table_data_pro->product_price }}" name="product_price" id="inputGroupPrepend2" aria-describedby="inputGroupPrepend2" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Sticky Product ID</label>
                                            <input type="number" class="form-control" value="{{ $table_data_pro->sticky_product_id }}" name="sticky_product_id" id="inputGroupPrepend2" aria-describedby="inputGroupPrepend2" placeholder="" required>
                                        </div>
                                    </div>

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Uniq ID</label>
                                            <input type="text" class="form-control" value="{{ $table_data_pro->uniq_id }}" name="uniq_id" id="inputGroupPrepend2" aria-describedby="inputGroupPrepend2" placeholder="" required>
                                        </div>
                                    </div>
                                    {{-- <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select Category</label>
                                            <select class="form-control mb-3" name="category_id" id="category_id" required>
                                                <option value="">Select Category</option>
                                                @foreach ($table_data as $benifit_category)
                                                <option value="{{ $benifit_category->category_id }}" {{ $benifit_category->category_id == $table_data_pro->category_id ? 'selected' : '' }}>
                                                    {{ $benifit_category->category_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div> --}}

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select Site</label>
                                            <select class="form-control mb-3" name="id" id="id" required>
                                                <option value="">Select Category</option>
                                                @foreach ($site_data as $benifit_category)
                                                <option value="{{ $benifit_category->id }}" {{ $benifit_category->id == $table_data_pro->site_id ? 'selected' : '' }}>
                                                    {{ $benifit_category->site_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Sku</label>
                                            <input type="text" class="form-control" value="{{ $table_data_pro->product_sku }}" name="sku" id="inputGroupPrepend2" aria-describedby="inputGroupPrepend2" placeholder="" >
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product description</label>
                                            <textarea class="form-control" id="product_description" name="product_description" rows="4" required>{{ $table_data_pro->product_description }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Product Image</h4>


                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter" style="float: right;">Add Product Image</button>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Product Image</th>
                                        <th>Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product_image as $data)

                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        {{-- <td>{{ $data->category_name}}</td> --}}
                                        <td>
                                            <a href="{{ URL::asset('public/assets/product_img/' . $data->img_video) }}" target="_blank" style="cursor: pointer;"><img src="{{ URL::asset('public/assets/product_img/' . $data->img_video) }}" width="32" height="32" class="rounded-circle mr-2" alt="Avatar">
                                            </a>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter2{{ $data->product_image_id  }}" style="float: right;">Edit</button>
                                            <button type="button" class="btn btn-danger mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter3{{ $data->product_image_id  }}" style="float: right;">Delete</button>


                                        </td>

                                    </tr>
                                    {{-- //edit --}}
                                    <div class="modal fade" id="exampleModalCenter2{{ $data->product_image_id  }}">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="card-body">
                                                    <div class="form-validation">
                                                        <form method="POST" action="{{ url('edit_product_img', $data->product_image_id ) }}" enctype="multipart/form-data"> @csrf
                                                            <?php $admin_id = session('SubAdminID'); ?>
                                                            <div class="row">
                                                                <div class="modal-body">
                                                                    <div class="form-group">
                                                                        <label for="Blog" class="form-label">Product Image</label>
                                                                        <input type="file" name="img_video" class="form-control file_upload" id="img_video" accept="image/*" onchange="sub_service_file1_{{ $data->product_image_id }}(this);" value="{{ $data->img_video }}">
                                                                    </div>

                                                                </div>
                                                                <div class="col-md-4">
                                                                    <img id="sub_service_view1_{{ $data->product_image_id }}" src="{{ URL::asset('public/assets/product_img/' . $data->img_video) }}" alt="your image" height="100;" width="100;" class="img_details_return" />
                                                                </div>
                                                                <script>
                                                                    function sub_service_file1_ {
                                                                        {
                                                                            $data - > product_image_id
                                                                        }
                                                                    }(input) {
                                                                        if (input.files && input.files[0]) {
                                                                            var reader = new FileReader();
                                                                            reader.onload = function(e) {
                                                                                $('#sub_service_view1_{{ $data->product_image_id }}').attr('src', e.target.result).width(100).height(100);
                                                                            };
                                                                            reader.readAsDataURL(input.files[0]);
                                                                        }
                                                                    }

                                                                </script>

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

                                    {{-- //delete --}}
                                    <div class="modal fade" id="exampleModalCenter3{{ $data->product_image_id  }}">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="card-body">
                                                    <div class="form-validation">
                                                        <form method="POST" action="{{ url('delete_product_img', $data->product_image_id) }}" enctype="multipart/form-data"> @csrf
                                                            <?php $admin_id = session('SubAdminID'); ?>

                                                            {{-- <div class="modal-header bg-primary"> --}}
                                                                <h5 >
                                                                    Delete Product image
                                                                </h5>
                                                                {{-- <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                                                    <i data-feather="x"></i>
                                                                </button> --}}
                                                            </div>
                                                            <div class="row">


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
    <!-- Modal -->
    <div class="modal fade" id="exampleModalCenter">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="card-body">
                    <div class="form-validation">
                        <form method="POST" action="{{ route('add_product_image') }}" enctype="multipart/form-data"> @csrf
                            <?php $admin_id = session('SubAdminID'); ?>
                            <div class="row">
                                <div class="col-lg-12 mb-6">
                                    <div class="mb-3">
                                        {{-- <div class="form-group"> --}}
                                        <label for="Categories" class="form-label">Product Image</label>
                                        <input type="hidden" value="{{ $table_data_pro->product_id }}" name="product_id">
                                        <input type="file" name="img_video" id="img_video" class="form-control" placeholder="Enter Image" onchange="sub_service_file(this);" data-parsley-required="true" required>
                                        {{-- </div> --}}
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <img id="sub_service_view" src="{{ URL::asset('public/assets/logo/upload_img.png') }}" alt="your image" class="img_details2" />
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
</div>
<script type="text/javascript">
    function sub_service_file(input) {

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#sub_service_view')
                    .attr('src', e.target.result)
                    .width(100)
                    .height(100);
            };

            reader.readAsDataURL(input.files[0]);
        }
    }

</script>
<script>
    document.getElementById('addproductForm').addEventListener('submit', function(event) {
        var formUrl = document.getElementById('form_url').value;
        var formIdMatch = formUrl.match(/(\d+)$/);
        if (formIdMatch) {
            document.getElementById('form_id').value = formIdMatch[1];
        }
    });

</script>

@include('include.footer')
{{-- @include('include.message') --}}
