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
                        <h4 class="card-title">Patients</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Prefix</th>
                                        <th>Patients First Name</th>
                                        <th>Patients Last Name</th>
                                        <th>Partner Name</th>
                                        <th>Patient Id</th>
                                        <th>Email</th>
                                        <th>Date of birth</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)
    
                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->prefix}}</td>
                                        <td><a href="{{ url('user_details', $data->user_id) }}" class="underline">{{ $data->first_name}}</a></td>
                                        <td>{{ $data->last_name}}</td>
                                        <td>{{ $data->partner_name }}</td>
                                        <td>{{ $data->patient_id }}</td>
                                        <td>{{ $data->email }}</td>
                                        <td>{{ $data->date_of_birth}}</td>
                                        {{-- <td>{{ $data->user_id}}</td> --}}
    
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