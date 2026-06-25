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
                        <h4 class="card-title">Cases</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>First Name</th>
                                        <th>Email</th>
                                        <th>Case Id</th>
                                        <th>Patient Id</th>
                                   
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)
    
                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->first_name}}</td>
                                        <td>{{ $data->email}}</td>

                                        {{-- <td><a href="{{ url('cases_details', $data->case_id) }}" class="underline">{{ $data->case_id }}</a></td> --}}
                                        <td><a href="https://app.mdintegrations.com/tabs/cases/{{ $data->case_id }}" class="underline">{{ $data->case_id }}</a></td>

                                        <td><a href="{{ url('cases_details', $data->case_id) }}" class="underline">{{ $data->patient_id}}</td>
                             
                                        
                                        
    
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