<?php

namespace AhgMarketplace\Controllers;

use App\Http\Controllers\Controller;

class MarketplaceController extends Controller
{
    public function adminCategories() { return view('marketplace::admin-categories'); }

    public function adminCurrencies() { return view('marketplace::admin-currencies'); }

    public function adminDashboard() { return view('marketplace::admin-dashboard'); }

    public function adminListingReview() { return view('marketplace::admin-listing-review'); }

    public function adminListings() { return view('marketplace::admin-listings'); }

    public function adminPayouts() { return view('marketplace::admin-payouts'); }

    public function adminReports() { return view('marketplace::admin-reports'); }

    public function adminReviews() { return view('marketplace::admin-reviews'); }

    public function adminSellerVerify() { return view('marketplace::admin-seller-verify'); }

    public function adminSellers() { return view('marketplace::admin-sellers'); }

    public function adminSettings() { return view('marketplace::admin-settings'); }

    public function adminTransactions() { return view('marketplace::admin-transactions'); }

    public function auctionBrowse() { return view('marketplace::auction-browse'); }

    public function bidForm() { return view('marketplace::bid-form'); }

    public function browse() { return view('marketplace::browse'); }

    public function category() { return view('marketplace::category'); }

    public function collection() { return view('marketplace::collection'); }

    public function dashboard() { return view('marketplace::dashboard'); }

    public function enquiryForm() { return view('marketplace::enquiry-form'); }

    public function featured() { return view('marketplace::featured'); }

    public function listing() { return view('marketplace::listing'); }

    public function myBids() { return view('marketplace::my-bids'); }

    public function myFollowing() { return view('marketplace::my-following'); }

    public function myOffers() { return view('marketplace::my-offers'); }

    public function myPurchases() { return view('marketplace::my-purchases'); }

    public function offerForm() { return view('marketplace::offer-form'); }

    public function reviewForm() { return view('marketplace::review-form'); }

    public function search() { return view('marketplace::search'); }

    public function sector() { return view('marketplace::sector'); }

    public function sellerAnalytics() { return view('marketplace::seller-analytics'); }

    public function sellerCollectionCreate() { return view('marketplace::seller-collection-create'); }

    public function sellerCollections() { return view('marketplace::seller-collections'); }

    public function sellerEnquiries() { return view('marketplace::seller-enquiries'); }

    public function sellerListingCreate() { return view('marketplace::seller-listing-create'); }

    public function sellerListingEdit() { return view('marketplace::seller-listing-edit'); }

    public function sellerListingImages() { return view('marketplace::seller-listing-images'); }

    public function sellerListings() { return view('marketplace::seller-listings'); }

    public function sellerOfferRespond() { return view('marketplace::seller-offer-respond'); }

    public function sellerOffers() { return view('marketplace::seller-offers'); }

    public function sellerPayouts() { return view('marketplace::seller-payouts'); }

    public function sellerProfile() { return view('marketplace::seller-profile'); }

    public function sellerRegister() { return view('marketplace::seller-register'); }

    public function sellerReviews() { return view('marketplace::seller-reviews'); }

    public function seller() { return view('marketplace::seller'); }

    public function sellerTransactionDetail() { return view('marketplace::seller-transaction-detail'); }

    public function sellerTransactions() { return view('marketplace::seller-transactions'); }

}
