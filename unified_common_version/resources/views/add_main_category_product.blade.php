@include('include.header')

<head>

</head>
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Add Main Category Product Data</h4>


                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter" style="float: right;">Add New Category Product</button>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Product Name</th>
                                        <th>Sticky Product Id</th>
                                        <th>index</th>
                                        <th>Dosage</th>
                                        <th>Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)

                                    <tr>
                                        
                                        <td> {{ $loop->iteration }}</td>
                                        {{-- <td> {{ $loop->iteration }}</td> --}}
                                        <td>{{ $data->product_name }}</td>
                                        <td>{{ $data->sticky_product_id }}</td>
                                        <td>{{ $data->index }}</td>
                                        <td>
                                            {{ extractDosage($data->product_name) }}
                                        </td>
                                        <td>
                                            {{-- <a href="" data-bs-target="#exampleModalCenter_2{{ $data->category_product_data_id }}" class="btn btn-primary shadow btn-xs sharp me-1"><i
                                                class="fas fa-pencil-alt"></i></a> --}}
                                                <button type="button" class="btn btn-primary shadow btn-xs sharp me-1" data-bs-toggle="modal" data-bs-target="#exampleModalCenter_2{{ $data->category_product_data_id }}">
                                                    <i class="fas fa-pencil-alt"></i></a></button>
                                        </td>

                                    </tr>

                                    <div class="modal fade" id="exampleModalCenter_2{{ $data->category_product_data_id }}">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="card-body">
                                                    <div class="form-validation">
                                                        <form method="POST" action="{{ url('edit_main_category_product') }}" id="addproductForm" enctype="multipart/form-data">
                                                            @csrf
                                                            <?php $admin_id = session('SubAdminID'); ?>
                                                            <div class="row">
                                                                <div class="col-lg-12 mb-6">
                                                                    <div class="mb-3">
                                                                        <label class="text-label form-label">Change Index</label>
                                                                        {{-- <select class="form-control mb-3" name="product_id" id="category_id" required>
                                                                            <option value="">Select Product</option>
                                                                            @foreach ($product_data as $benifit_category)
                                                                            <option value="{{ $benifit_category->product_id }}">
                                                                                {{ $benifit_category->product_name }}</option>
                                                                            @endforeach
                                                                        </select> --}}
                                                                        <input type="number" value="{{ $data->index }}" name="index" class="form-control mb-3">
                                                                        <input type="hidden" value="{{ $data->category_product_data_id }}"  name="category_product_data_id" class="form-control mb-3">
                                    
                                                                    </div>
                                                                    
                                                                    <input type="hidden" name="category_product_id" value="{{ $category_product_id }}" class="form-control" placeholder="" >
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
                <div class="form-validation">
                    <form method="POST" action="{{ url('add_new_category_product_data') }}" id="addproductForm" enctype="multipart/form-data">
                        @csrf
                        <?php $admin_id = session('SubAdminID'); ?>
                        <div class="row">
                            <div class="col-lg-12 mb-6">
                                <div class="mb-3">
                                    <label class="text-label form-label">Select Product</label>
                                    <select class="form-control mb-3" name="product_id" id="category_id" required>
                                        <option value="">Select Product</option>
                                        @foreach ($product_data as $benifit_category)
                                        <option value="{{ $benifit_category->product_id }}">
                                            {{ $benifit_category->product_name }}</option>
                                        @endforeach
                                    </select>

                                </div>
                                
                                <input type="hidden" name="category_product_id" value="{{ $category_product_id }}" class="form-control" placeholder="" >
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

@php
function extractDosage($productName) {
    preg_match('/\d+\.?\d*\s?(mg|MG)/', $productName, $matches);
    return $matches[0] ?? 'N/A';
}
@endphp

@include('include.footer')
@include('include.message')
