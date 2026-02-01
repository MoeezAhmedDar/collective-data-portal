@extends('layouts.app')
@section('content')
    <main id="main">
        <div class="page-title-box">
            <div class="container">
                <div class="row flex-align">
                    <div class="col">
                        <h1 class="page-super-title">Super Admin</h1>
                        <div class="flex-align gap-30">
                            <h4 class="page-title">Dashboard</h4>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="">IRCC Data Portal</a></li>
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="page-title-right">
                            <h4 class="page-title">Filter By :</h4>

                            <div class="dropdown">
                                <a class="custom-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-title">Jhon Doe</div>
                                    <i class="fas fa-chevron-down"></i>
                                </a>
                                <ul class="dropdown-menu option-sub-menu sale-sub-menu">
                                    <li><a href="#">annually</a></li>
                                    <li><a href="#">Monthly</a></li>
                                    <li><a href="#">Quarterly</a></li>
                                    <li><a href="#">Daily</a></li>
                                    <li><a href="#">Custom</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="container">
                <div class="card-row" id="report-carousel">
                    <div class="report-card item">
                        <div class="card-body">
                            <h1 class="card-title">{{ $retailersCount }}</h1>
                            <h2 class="card-subtitle">Retailer</h2>
                        </div>
                    </div>

                    <div class="report-card item">
                        <div class="card-body">
                            <h1 class="card-title">{{ $lpsCount }}</h1>
                            <h2 class="card-subtitle">Licence Provider</h2>
                        </div>
                    </div>

                    <div class="report-card item">
                        <div class="card-body">
                            <h1 class="card-title">{{ $reportsCount }}</h1>
                            <h2 class="card-subtitle">Reports Generated</h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="row content-row">
                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title">Sales Report</h4>
                            <div class="img-box">
                                <img src="{{ asset('admin/images/graph-1.png') }}" alt="">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title">Revenue</h4>
                            <div class="img-box">
                                <img src="{{ asset('admin/images/graph-2.png') }}" alt="">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row content-row">

                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title">Area Sales</h4>
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="ímg-box">
                                        <img src="{{ asset('admin/images/graph-3.png') }}" alt="">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <h6 class="text-capitalize mb-4 text-primary fw-500">Last Month Revenue</h6>
                                    <div class="text-box py-3 border-top-gray">
                                        <div class="space-between mb-2">
                                            <div class="fw-500">Ontario</div>
                                            <div class="fw-500 text-black flex-align gap-2">2.52% <i
                                                    class="far fa-arrow-up text-green"></i></div>
                                        </div>
                                        <h4 class="text-mischka-gray mb-0 fw-500">$5643</h4>
                                    </div>
                                    <div class="text-box py-3 border-top-gray">
                                        <div class="space-between mb-2">
                                            <div class="fw-500">Quebec</div>
                                            <div class="fw-500 text-black flex-align gap-2">1.52% <i
                                                    class="far fa-arrow-down text-red"></i></div>
                                        </div>
                                        <h4 class="text-mischka-gray mb-0 fw-500">$120</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title border-bottom-gray">Recent Activity Feed</h4>
                            <ul class="activity-list">
                                @foreach ($activites as $activity)
                                    <li>
                                        <div class="fw-bold">{{ $activity->created_at->format('d-m-Y') }}</div>
                                        <p>{{ $activity->activity }}”</p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="row content-row">
                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title">Retailers</h4>
                            <div class="table-responsive" id="retailer_table">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Province</th>
                                            <th>Locations</th>
                                            <th>Report Status</th>
                                            <th>Submission Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($retailers as $retailer)
                                            @php
                                                // Get the most recent report status (assuming 'date' is a date/datetime column)
                                                $latestStatus = $retailer->ReportStatus->sortByDesc('date')->first();
                                            @endphp

                                            <tr>
                                                <td>
                                                    <div class="user-img">
                                                        <img src="{{ $retailer->user?->avatar ?? asset('admin/images/user-02.png') }}"
                                                            alt="{{ $retailer->user?->name ?? 'Retailer' }}"
                                                            class="rounded-circle">
                                                    </div>
                                                    <div class="user-title">
                                                        {{ $retailer->user?->name ?? 'Unknown User' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    {{ $latestStatus?->province ?? '—' }}
                                                </td>
                                                <td>
                                                    {{ $latestStatus?->location ?? '—' }}
                                                </td>
                                                <td>
                                                    <div class="flex-align gap-2">
                                                        @if ($latestStatus)
                                                            <span
                                                                class="status {{ strtolower($latestStatus->status) ?? 'pending' }}"></span>
                                                            {{ ucfirst($latestStatus->status ?? 'Pending') }}
                                                        @else
                                                            <span class="status pending"></span>
                                                            Pending
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    {{ $latestStatus?->date?->format('d M Y') ?? '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    No retailers found
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <a class="btn btn-primary mt-3" href="{{ route('retailers.index') }}">
                                Show All Retailers
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="content-box">
                            <h4 class="content-title">Licence Producer</h4>
                            <div class="table-responsive" id="lp_table">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Province</th>
                                            <th>Offer</th>
                                            <th>Invoice Status</th>
                                        </tr>
                                    </thead>

                                    <tbody id="ruleTable">
                                        @foreach ($lps as $lp)
                                            <tr>
                                                <td>
                                                    <div class="user-img">
                                                        <img src="{{ asset('admin/images/user-02.png') }}"
                                                            alt="">
                                                    </div>
                                                    <div class="user-title">{{ $lp->user->name }}</div>
                                                </td>
                                                <td>{{ $lp->LpAddresses->province }}</td>
                                                <td>2</td>
                                                <td>
                                                    <div class="flex-align gap-2">
                                                        <span class="status pending"></span>
                                                        Pending
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <a class="btn btn-primary" href="{{ route('lps.index') }}">Show All</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@stop
