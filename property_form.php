<style>
    .form-label-md { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.2rem; color: #1e293b; }
    .form-control-md { font-size: 0.9rem; padding: 0.35rem 0.6rem; height: calc(2rem + 2px); border-radius: 6px; }
    .form-control-lg-custom { font-size: 1rem; padding: 0.4rem 0.75rem; height: calc(2.3rem + 2px); border-radius: 6px; }
    .select-md { font-size: 0.9rem; padding: 0.35rem 0.6rem; height: calc(2rem + 2px); border-radius: 6px; }
</style>

<div class="row g-2">

    <!-- TITLE & ADDRESS -->
    <div class="col-md-6">
        <label class="form-label-md">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" id="edit_title" class="form-control form-control-lg-custom" required value="">
    </div>
    <div class="col-md-6">
        <label class="form-label-md">Address / Location <span class="text-danger">*</span></label>
        <input type="text" name="location" id="edit_location" class="form-control form-control-lg-custom" required value="">
    </div>

    <!-- PRICE -->
    <div class="col-md-3">
        <label class="form-label-md">Reserve Price (₹) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="price" id="edit_price" class="form-control form-control-md" required value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Price per Sq Ft</label>
        <input type="number" step="0.01" name="reserve_price_per_sqft" id="edit_reserve_price_per_sqft" class="form-control form-control-md" value="">
    </div>

    <!-- BORROWER & BANK -->
    <div class="col-md-3">
        <label class="form-label-md">Borrower Name</label>
        <input type="text" name="borrower_name" id="edit_borrower_name" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Bank Name</label>
        <input type="text" name="bank_name" id="edit_bank_name" class="form-control form-control-md" value="">
    </div>

    <!-- TYPE & POSSESSION -->
    <div class="col-md-3">
        <label class="form-label-md">Property Type</label>
        <select name="type" id="edit_type" class="form-control select-md">
            <option value="Flat">Flat</option>
            <option value="Plot">Plot</option>
            <option value="Shop">Shop</option>
            <option value="Land">Land</option>
            <option value="House">House</option>
            <option value="Row House">Row House</option>
            <option value="Bungalow">Bungalow</option>
            <option value="Car">Car</option>
            <!-- ✅ NEW OPTIONS -->
            <option value="Land & Building">Land & Building</option>
            <option value="Hotel">Hotel</option>
            <option value="Factory">Factory</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Possession</label>
        <select name="possession_type" id="edit_possession_type" class="form-control select-md">
            <option value="Physical">Physical</option>
            <option value="Symbolic">Symbolic</option>
        </select>
    </div>

    <!-- LOCALITY, CITY, STATE -->
    <div class="col-md-3">
        <label class="form-label-md">Locality</label>
        <input type="text" name="locality" id="edit_locality" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">City <span class="text-danger">*</span></label>
        <input type="text" name="city" id="edit_city" class="form-control form-control-md" required value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">State</label>
        <input type="text" name="state" id="edit_state" class="form-control form-control-md" value="">
    </div>

    <!-- EMD, BID, AREA -->
    <div class="col-md-3">
        <label class="form-label-md">EMD Amount (₹)</label>
        <input type="number" step="0.01" name="emd_amount" id="edit_emd_amount" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Bid Increment (₹)</label>
        <input type="number" step="0.01" name="bid_increment" id="edit_bid_increment" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Area (Sq Ft)</label>
        <input type="number" step="0.01" name="sqft" id="edit_sqft" class="form-control form-control-md" value="">
    </div>

    <!-- AUCTION START / END / DEADLINE -->
    <div class="col-md-3">
        <label class="form-label-md">EMD Submission Deadline</label>
        <input type="text" name="emd_deadline" id="edit_emd_deadline" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Auction Start Date & Time</label>
        <input type="text" name="auction_start_time" id="edit_auction_start_time" class="form-control form-control-md" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-md">Auction End Date & Time</label>
        <input type="text" name="auction_end_time" id="edit_auction_end_time" class="form-control form-control-md" value="">
    </div>

    <!-- INSPECTION DATE -->
    <div class="col-md-3">
        <label class="form-label-md">Inspection Date (DD/MM/YYYY)</label>
        <input type="text" name="inspection_date" id="edit_inspection_date" class="form-control form-control-md" value="">
    </div>

    <!-- AUCTION DATE (NEW) -->
    <div class="col-md-3">
        <label class="form-label-md">Auction Date (DD/MM/YYYY)</label>
        <input type="text" name="auction_date" id="edit_auction_date" class="form-control form-control-md" value="">
    </div>

    <!-- CONTACT NUMBER (hidden or visible) -->
    <div class="col-md-3">
        <label class="form-label-md">Contact Number</label>
        <input type="text" name="contact_number" id="edit_contact_number" class="form-control form-control-md" value="">
    </div>

    <!-- GOOGLE LOCATION (hidden) -->
    <input type="hidden" name="google_location" id="edit_google_location" value="">

    <!-- DESCRIPTION (hidden or we can show) -->
    <div class="col-12">
        <label class="form-label-md">Description</label>
        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
    </div>

    <!-- IMAGE UPLOAD (optional) -->
    <div class="col-12">
        <label class="form-label-md">Image</label>
        <input type="file" name="image_file" id="image_file" class="form-control" accept="image/*">
        <small id="imageHelpText" class="text-muted">Leave empty to auto-generate premium social card.</small>
        <div id="currentImagePreview" style="display:none; margin-top:10px;">
            <img id="currentImage" src="" style="max-height:150px; border-radius:8px; border:1px solid #ddd; padding:4px;">
        </div>
    </div>
</div>
