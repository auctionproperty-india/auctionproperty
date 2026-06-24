<style>
    /* फॉर्म के अंदर सभी Inputs और Labels को थोड़ा छोटा करें */
    .form-control-sm-custom {
        font-size: 0.85rem;
        padding: 0.25rem 0.5rem;
        height: calc(1.8rem + 2px);
    }
    .form-label-sm {
        font-size: 0.75rem;
        margin-bottom: 0.2rem;
        font-weight: 600;
    }
    .select-sm {
        font-size: 0.85rem;
        padding: 0.25rem 0.5rem;
        height: calc(1.8rem + 2px);
    }
    /* Title और Address के लिए बड़े Inputs */
    .form-control-lg-custom {
        font-size: 1rem;
        padding: 0.375rem 0.75rem;
        height: calc(2.25rem + 2px);
    }
</style>

<div class="row g-2"> <!-- g-2 से Gap कम करें -->

    <!-- ===== TITLE (Big) ===== -->
    <div class="col-md-12">
        <label class="form-label-sm">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" id="edit_title" class="form-control form-control-lg-custom" required value="">
    </div>

    <!-- ===== ADDRESS (Big) ===== -->
    <div class="col-md-12">
        <label class="form-label-sm">Address / Location <span class="text-danger">*</span></label>
        <input type="text" name="location" id="edit_location" class="form-control form-control-lg-custom" required value="">
    </div>

    <!-- ===== RESERVE PRICE & PRICE PER SQ FT ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">Reserve Price (₹) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="price" id="edit_price" class="form-control form-control-sm-custom" required value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Price per Sq Ft</label>
        <input type="number" step="0.01" name="reserve_price_per_sqft" id="edit_reserve_price_per_sqft" class="form-control form-control-sm-custom" value="">
    </div>

    <!-- ===== BORROWER & BANK ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">Borrower Name</label>
        <input type="text" name="borrower_name" id="edit_borrower_name" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Bank Name</label>
        <input type="text" name="bank_name" id="edit_bank_name" class="form-control form-control-sm-custom" value="">
    </div>

    <!-- ===== PROPERTY TYPE & POSSESSION ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">Property Type</label>
        <select name="type" id="edit_type" class="form-control select-sm">
            <option value="Flat">Flat</option>
            <option value="Plot">Plot</option>
            <option value="Shop">Shop</option>
            <option value="Land">Land</option>
            <option value="House">House</option>
            <option value="Row House">Row House</option>
            <option value="Bungalow">Bungalow</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Possession</label>
        <select name="possession_type" id="edit_possession_type" class="form-control select-sm">
            <option value="Physical">Physical</option>
            <option value="Symbolic">Symbolic</option>
        </select>
    </div>

    <!-- ===== LOCALITY, CITY, STATE ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">Locality</label>
        <input type="text" name="locality" id="edit_locality" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">City <span class="text-danger">*</span></label>
        <input type="text" name="city" id="edit_city" class="form-control form-control-sm-custom" required value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">State</label>
        <input type="text" name="state" id="edit_state" class="form-control form-control-sm-custom" value="">
    </div>

    <!-- ===== EMD, BID, AREA ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">EMD Amount (₹)</label>
        <input type="number" step="0.01" name="emd_amount" id="edit_emd_amount" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Bid Increment (₹)</label>
        <input type="number" step="0.01" name="bid_increment" id="edit_bid_increment" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Area (Sq Ft)</label>
        <input type="number" step="0.01" name="sqft" id="edit_sqft" class="form-control form-control-sm-custom" value="">
    </div>

    <!-- ===== DATES & TIMES ===== -->
    <div class="col-md-3">
        <label class="form-label-sm">EMD Submission Deadline</label>
        <input type="text" name="emd_deadline" id="edit_emd_deadline" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Auction Start Date & Time</label>
        <input type="text" name="auction_start_time" id="edit_auction_start_time" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Auction End Date & Time</label>
        <input type="text" name="auction_end_time" id="edit_auction_end_time" class="form-control form-control-sm-custom" value="">
    </div>
    <div class="col-md-3">
        <label class="form-label-sm">Auction Date (DD/MM/YYYY)</label>
        <input type="text" name="auction_date" id="edit_auction_date" class="form-control form-control-sm-custom" value="">
    </div>

    <!-- ===== CONTACT NUMBER HIDDEN ===== -->
    <input type="hidden" name="contact_number" id="edit_contact_number" value="<?= $default_contact ?>">

    <!-- ===== IMAGE UPLOAD ===== -->
    <div class="col-12">
        <label class="form-label-sm">Upload Image</label>
        <div id="currentImagePreview" style="display:none; margin-bottom:5px;">
            <img id="currentImage" src="" style="max-height:80px; border-radius:8px; border:1px solid #ddd;">
        </div>
        <input type="file" name="image_file" id="edit_image_file" class="form-control form-control-sm-custom" accept="image/*">
        <small class="text-muted" style="font-size:0.7rem;">Leave empty to auto-generate a social card.</small>
    </div>
</div>
