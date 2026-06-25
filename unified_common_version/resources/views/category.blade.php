@include('include.header')
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Category</h4>


                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter" style="float: right;">Add New Category</button>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Category Name</th>
                                        <th>Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)

                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->category_name}}</td>
                                        <td>
                                            <a href="{{ url('add_category_product', $data->category_id) }}" class="btn btn-primary shadow btn-xs sharp me-1"><i
                                                class="fas fa-pencil-alt"></i></a>
                         
                                        </td>

                                    </tr>
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
                    <form method="POST" action="{{ url('add_category') }}" id="addproductForm" enctype="multipart/form-data">
                        @csrf
                        <?php $admin_id = session('SubAdminID'); ?>
                        <div class="row">
                            <div class="col-lg-12 mb-6">
                                <div class="mb-3">
                                    <label class="text-label form-label">Category Name</label>
                                    <input type="text" name="category_name" class="form-control" placeholder="" required>
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

@include('include.footer')
{{-- @include('include.message') --}}
