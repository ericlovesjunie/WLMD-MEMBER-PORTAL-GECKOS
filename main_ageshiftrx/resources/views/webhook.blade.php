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
                        <h4 class="card-title">webhook</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Webhook ID</th>
                                        <th>Unique ID</th>
                                        <th>Full Name</th>
                                        <th>Form ID</th>
                                        <th>Submission ID</th>
                                        <th>Tracking UNID</th>
                                        <th>Form Title</th>
                                        <th>Created At</th>


                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)

                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        {{-- <td><a href="{{ url('webhook_details', $data['webhook_id']) }}" class="underline">{{ $data['webhook_id'] }}</a></td> --}}
                                        <td>{{ $data['webhook_id'] }}</td>
                                        <td>{{ $data['unique_id'] }}</td>
                                        <td>{{ $data['fullName'] }}</td>
                                        <td>{{ $data['form_id'] }}</td>
                                        <td>{{ $data['submission_id'] }}</td>
                                        <td>{{ $data['tracking_unid'] }}</td>
                                        <td>{{ $data['form_title'] }}</td>
                                        <td>{{ $data['created_at'] }}</td>



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

@include('include.footer')
