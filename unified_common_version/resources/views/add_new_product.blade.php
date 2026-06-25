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
                        <h4 class="card-title">Add New Product</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-validation">
                            <form method="POST" action="{{ url('add_product') }}" id="addproductForm" enctype="multipart/form-data">
                                @csrf
                                <?php $admin_id = session('SubAdminID'); ?>
                                <div class="row">
                                    <div class="col-lg-12 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product Name</label>
                                            <input type="text" name="product_name" class="form-control" placeholder="" required>
                                            <input type="hidden" class="form-control" name="form_id" id="form_id">
                                        </div>
                                    </div>
                                    {{-- <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Form URL</label>
                                            <input type="text" name="form_url"id="form_url" class="form-control" placeholder="" required>
                                        </div>
                                    </div> --}}

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Sticky Product Id</label>
                                            <input type="number" name="sticky_product_id" id="form_url" class="form-control"  pattern="[0-9]*" inputmode="numeric" aria-describedby="inputGroupPrepend2" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product Price</label>
                                            <input type="number" class="form-control" name="product_price" id="inputGroupPrepend2"  pattern="[0-9]*" inputmode="numeric" aria-describedby="inputGroupPrepend2" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select Category</label>
                                            <select class="form-control mb-3" name="category_id" id="category_id" required>
                                                <option value="">Select Category</option>
                                                @foreach ($table_data as $benifit_category)
                                                
                                                <option value="{{ $benifit_category->category_id }}">
                                                    {{ $benifit_category->category_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select Site</label>
                                            <select class="form-control mb-3" name="id" id="id" required>
                                                <option value="">Select Site</option>
                                                @foreach ($site_data as $benifit_category)
                                                {{-- <input type="hidden" name="site_id" value="{{ $benifit_category->site_id }}"/> --}}
                                                <option value="{{ $benifit_category->id }}">
                                                    {{ $benifit_category->site_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product description</label>
                                            <textarea type="text" name="product_description" class="form-control" required></textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <div id="image-container">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Product Images</label>
                                            <div class="image-preview"></div>
                                            <input type="file" name="img_video[]" class="form-control" id="img_video" multiple accept="image/*,video/*,audio/*" required>
                                        </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary add_new-btn" id="add-image" style="float: right;">Add</button>
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
</div>
<script>
    $(document).ready(function() {
        // Add image/video
        $('#add-image').click(function() {
            $('#image-container').append(
                '<div class="form-group">' +
                '<label class="form-label">Add Your Images</label>' +
                '<div class="image-preview"></div>' +
                '<input type="file" name="img_video[]" class="form-control" multiple accept="image/*,video/*,audio/*">' +
                '<button type="button" class="btn btn-danger remove-image">Remove</button>' +
                '</div>'
            );
        });

        // Remove image/video
        $(document).on('click', '.remove-image', function() {
            $(this).parent().remove();
        });

        // Preview image/video
        $(document).on('change', 'input[type="file"]', function() {
            var files = $(this)[0].files;
            var preview = $(this).siblings('.image-preview');
            preview.empty();
            if (files.length > 0) {
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var type = file.type.split('/')[0];
                        if (type == 'image') {
                            preview.append('<img src="' + e.target.result + '" class="img-thumbnail">');
                        } else if (type == 'video') {
                            preview.append(
                                '<video width="320" height="240" controls>' +
                                '<source src="' + e.target.result + '" type="' + file.type + '">' +
                                'Your browser does not support the video tag.' +
                                '</video>'
                            );
                        }
                    }
                    reader.readAsDataURL(file);
                }
            }
        });
    });

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