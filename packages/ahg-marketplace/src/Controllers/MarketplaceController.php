<?php

namespace AhgMarketplace\Controllers;

use App\Http\Controllers\Controller;

class MarketplaceController extends Controller
{
    public function adminCategories() { return view('spectrum::admin-categories'); }

    public function adminCurrencies() { return view('spectrum::admin-currencies'); }

    public function adminDashboard() { return view('spectrum::admin-dashboard'); }

    public function adminListingReview() { return view('spectrum::admin-listing-review'); }

    public function adminListings() { return view('spectrum::admin-listings'); }

    public function adminPayouts() { return view('spectrum::admin-payouts'); }

    public function adminReports() { return view('spectrum::admin-reports'); }

    public function adminReviews() { return view('spectrum::admin-reviews'); }

    public function adminSellerVerify() { return view('spectrum::admin-seller-verify'); }

    public function adminSellers() { return view('spectrum::admin-sellers'); }

    public function adminSettings() { return view('spectrum::admin-settings'); }

    public function adminTransactions() { return view('spectrum::admin-transactions'); }

    public function auctionBrowse() { return view('spectrum::auction-browse'); }

    public function bidForm() { return view('spectrum::bid-form'); }

    public function browse() { return view('spectrum::browse'); }

    public function category() { return view('spectrum::category'); }

    public function collection() { return view('spectrum::collection'); }

    public function dashboard() { return view('spectrum::dashboard'); }

    public function enquiryForm() { return view('spectrum::enquiry-form'); }

    public function featured() { return view('spectrum::featured'); }

    public function listing() { return view('spectrum::listing'); }

    public function myBids() { return view('spectrum::my-bids'); }

    public function myFollowing() { return view('spectrum::my-following'); }

    public function myOffers() { return view('spectrum::my-offers'); }

    public function myPurchases() { return view('spectrum::my-purchases'); }

    public function offerForm() { return view('spectrum::offer-form'); }

    public function reviewForm() { return view('spectrum::review-form'); }

    public function search() { return view('spectrum::search'); }

    public function sector() { return view('spectrum::sector'); }

    public function sellerAnalytics() { return view('spectrum::seller-analytics'); }

    public function sellerCollectionCreate() { return view('spectrum::seller-collection-create'); }

    public function sellerCollections() { return view('spectrum::seller-collections'); }

    public function sellerEnquiries() { return view('spectrum::seller-enquiries'); }

    public function sellerListingCreate() { return view('spectrum::seller-listing-create'); }

    public function sellerListingEdit() { return view('spectrum::seller-listing-edit'); }

    public function sellerListingImages() { return view('spectrum::seller-listing-images'); }

    public function sellerListings() { return view('spectrum::seller-listings'); }

    public function sellerOfferRespond() { return view('spectrum::seller-offer-respond'); }

    public function sellerOffers() { return view('spectrum::seller-offers'); }

    public function sellerPayouts() { return view('spectrum::seller-payouts'); }

    public function sellerProfile() { return view('spectrum::seller-profile'); }

    public function sellerRegister() { return view('spectrum::seller-register'); }

    public function sellerReviews() { return view('spectrum::seller-reviews'); }

    public function seller() { return view('spectrum::seller'); }

    public function sellerTransactionDetail() { return view('spectrum::seller-transaction-detail'); }

    public function sellerTransactions() { return view('spectrum::seller-transactions'); }

}
