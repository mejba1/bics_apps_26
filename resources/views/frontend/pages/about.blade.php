@extends('frontend.layouts.content')
@section('title')
   Contact
@endsection
@section('content')

<div class="breadcrumb-area">
    <div class="container">
       <div class="row align-items-end">
          <div class="col-lg-12">
             <div class="breadcrumb-content">
                <!-- <h2 class="page-title">Upcoming BICS</h2>-->
                <ul class="page-switcher">
                   <li><a href="index.php">Home <i class="bi bi-caret-right"></i></a></li>
                   <li>Contact</li>
                </ul>
             </div>
          </div>
       </div>
    </div>
 </div>
 <div class="blog-sidebar-wrapper mt-3 mb-5">
 <div class="container position-relative pt-10">
    <div class="row justify-content-center">
       <div class="col-md-4">
           <img src="{{ asset('assets/frontend') }}/images/contact-bics.png">
       </div>
       <div class="col-md-8">
          <div class="card shadow-lg rounded-10">
             <!--<span class="shape"></span>-->
             <div class="card-header text-bics"><i class="fas fa-info-circle fa-lg"></i> &nbsp;CONTACT FORM</div>
             <div class="card-body">
                <form id="msform" class="needs-validation" method="POST" action="#" enctype="multipart/form-data" novalidate>
                   <input type="hidden" name="_token" value="#">               
                   <div class="row">
                      <div class="col-xs-6 col-sm-6 col-md-6">
                         <label class="fieldlabels lebel-title">Name <span style="color:red">*</span></label>
                         <div class="form-group">
                            <div class="input-group input-group-merge input-group-alternative">
                               <input type="text" name="name" id="name" value="" class="form-control input-sm" placeholder="Enter Full Name" minlength="4" data-v-message="Please enter minimum 4 characters" required>
                            </div>
                            <span class="text-danger"></span>
                         </div>
                      </div>
                      <div class="col-xs-6 col-sm-6 col-md-6">
                         <label class="fieldlabels lebel-title">Email <span style="color:red">*</span></label>
                         <div class="form-group">
                            <div class="input-group input-group-merge input-group-alternative">
                               <input type="email" name="email" id="email" value="" class="form-control input-sm" placeholder="Enter Valid Email" minlength="4" data-v-message="Please enter minimum 4 characters" required>
                            </div>
                            <span class="text-danger"></span>
                         </div>
                      </div>
                   </div>
                   <div class="row mt-2">
                      <div class="col-xs-6 col-sm-6 col-md-6">
                         <label class="fieldlabels lebel-title">Country <span style="color:red">*</span></label>
                         <div class="form-group">
                            <div class="input-group input-group-merge input-group-alternative">
                               <select class="form-control input-sm data-control" id="caCountry" name="country"  data-container="body" data-live-search="true" title="Select One" data-hide-disabled="true" required="required">
                                  <option>Select Country</option>
                                  <option data-id="+93" value="Afghanistan">Afghanistan</option>
                                  <option data-id="+355" value="Albania">Albania</option>
                                  <option data-id="+213" value="Algeria">Algeria</option>
                                  <option data-id="+-683" value="American Samoa">American Samoa</option>
                                  <option data-id="+376" value="Andorra">Andorra</option>
                                  <option data-id="+244" value="Angola">Angola</option>
                                  <option data-id="+-263" value="Anguilla">Anguilla</option>
                                  <option data-id="+672" value="Antarctica">Antarctica</option>
                                  <option data-id="+-267" value="Antigua">Antigua</option>
                                  <option data-id="+54" value="Argentina">Argentina</option>
                                  <option data-id="+374" value="Armenia">Armenia</option>
                                  <option data-id="+297" value="Aruba">Aruba</option>
                                  <option data-id="+247" value="Ascension">Ascension</option>
                                  <option data-id="+61" value="Australia">Australia</option>
                                  <option data-id="+672" value="Australian External Territories">Australian External Territories</option>
                                  <option data-id="+43" value="Austria">Austria</option>
                                  <option data-id="+994" value="Azerbaijan">Azerbaijan</option>
                                  <option data-id="+-241" value="Bahamas">Bahamas</option>
                                  <option data-id="+973" value="Bahrain">Bahrain</option>
                                  <option data-id="+880" value="Bangladesh">Bangladesh</option>
                                  <option data-id="+-245" value="Barbados">Barbados</option>
                                  <option data-id="+-267" value="Barbuda">Barbuda</option>
                                  <option data-id="+375" value="Belarus">Belarus</option>
                                  <option data-id="+32" value="Belgium">Belgium</option>
                                  <option data-id="+501" value="Belize">Belize</option>
                                  <option data-id="+229" value="Benin">Benin</option>
                                  <option data-id="+-440" value="Bermuda">Bermuda</option>
                                  <option data-id="+975" value="Bhutan">Bhutan</option>
                                  <option data-id="+591" value="Bolivia">Bolivia</option>
                                  <option data-id="+387" value="Bosnia &amp; Herzegovina">Bosnia &amp; Herzegovina</option>
                                  <option data-id="+267" value="Botswana">Botswana</option>
                                  <option data-id="+55" value="Brazil">Brazil</option>
                                  <option data-id="+-283" value="British Virgin Islands">British Virgin Islands</option>
                                  <option data-id="+673" value="Brunei Darussalam">Brunei Darussalam</option>
                                  <option data-id="+359" value="Bulgaria">Bulgaria</option>
                                  <option data-id="+226" value="Burkina Faso">Burkina Faso</option>
                                  <option data-id="+257" value="Burundi">Burundi</option>
                                  <option data-id="+855" value="Cambodia">Cambodia</option>
                                  <option data-id="+237" value="Cameroon">Cameroon</option>
                                  <option data-id="+1" value="Canada">Canada</option>
                                  <option data-id="+238" value="Cape Verde Islands">Cape Verde Islands</option>
                                  <option data-id="+-344" value="Cayman Islands">Cayman Islands</option>
                                  <option data-id="+236" value="Central African Republic">Central African Republic</option>
                                  <option data-id="+235" value="Chad">Chad</option>
                                  <option data-id="+64" value="Chatham Island (New Zealand)">Chatham Island (New Zealand)</option>
                                  <option data-id="+56" value="Chile">Chile</option>
                                  <option data-id="+86" value="China (PRC)">China (PRC)</option>
                                  <option data-id="+53" value="Christmas Island">Christmas Island</option>
                                  <option data-id="+(08 from A" value=""></option>
                                  <option data-id="+61" value="Cocos-Keeling Islands">Cocos-Keeling Islands</option>
                                  <option data-id="+57" value="Colombia">Colombia</option>
                                  <option data-id="+269" value="Comoros">Comoros</option>
                                  <option data-id="+242" value="Congo">Congo</option>
                                  <option data-id="+243" value="Congo, Dem. Rep. of  (former Zaire)">Congo, Dem. Rep. of  (former Zaire)</option>
                                  <option data-id="+682" value="Cook Islands">Cook Islands</option>
                                  <option data-id="+506" value="Costa Rica">Costa Rica</option>
                                  <option data-id="+225" value="Côte d&#039;Ivoire (Ivory Coast)">Côte d&#039;Ivoire (Ivory Coast)</option>
                                  <option data-id="+385" value="Croatia">Croatia</option>
                                  <option data-id="+53" value="Cuba">Cuba</option>
                                  <option data-id="+5399" value="Cuba (Guantanamo Bay)">Cuba (Guantanamo Bay)</option>
                                  <option data-id="+599" value="Curaçao">Curaçao</option>
                                  <option data-id="+357" value="Cyprus">Cyprus</option>
                                  <option data-id="+420" value="Czech Republic">Czech Republic</option>
                                  <option data-id="+45" value="Denmark">Denmark</option>
                                  <option data-id="+246" value="Diego Garcia">Diego Garcia</option>
                                  <option data-id="+253" value="Djibouti">Djibouti</option>
                                  <option data-id="+-766" value="Dominica">Dominica</option>
                                  <option data-id="++1-809 and" value="Dominican Republic">Dominican Republic</option>
                                  <option data-id="+670" value="East Timor">East Timor</option>
                                  <option data-id="+56" value="Easter Island">Easter Island</option>
                                  <option data-id="+593" value="Ecuador">Ecuador</option>
                                  <option data-id="+20" value="Egypt">Egypt</option>
                                  <option data-id="+503" value="El Salvador">El Salvador</option>
                                  <option data-id="++8812, +88" value="Ellipso (Mobile Satellite service)">Ellipso (Mobile Satellite service)</option>
                                  <option data-id="+88213" value="EMSAT (Mobile Satellite service)">EMSAT (Mobile Satellite service)</option>
                                  <option data-id="+240" value="Equatorial Guinea">Equatorial Guinea</option>
                                  <option data-id="+291" value="Eritrea">Eritrea</option>
                                  <option data-id="+372" value="Estonia">Estonia</option>
                                  <option data-id="+251" value="Ethiopia">Ethiopia</option>
                                  <option data-id="+500" value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
                                  <option data-id="+298" value="Faroe Islands">Faroe Islands</option>
                                  <option data-id="+679" value="Fiji Islands">Fiji Islands</option>
                                  <option data-id="+358" value="Finland">Finland</option>
                                  <option data-id="+33" value="France">France</option>
                                  <option data-id="+596" value="French Antilles">French Antilles</option>
                                  <option data-id="+594" value="French Guiana">French Guiana</option>
                                  <option data-id="+689" value="French Polynesia">French Polynesia</option>
                                  <option data-id="+241" value="Gabonese Republic">Gabonese Republic</option>
                                  <option data-id="+220" value="Gambia">Gambia</option>
                                  <option data-id="+995" value="Georgia">Georgia</option>
                                  <option data-id="+49" value="Germany">Germany</option>
                                  <option data-id="+233" value="Ghana">Ghana</option>
                                  <option data-id="+350" value="Gibraltar">Gibraltar</option>
                                  <option data-id="+881" value="Global Mobile Satellite System (GMSS)">Global Mobile Satellite System (GMSS)</option>
                                  <option data-id="++8818, +88" value="Globalstar (Mobile Satellite Service)">Globalstar (Mobile Satellite Service)</option>
                                  <option data-id="+30" value="Greece">Greece</option>
                                  <option data-id="+299" value="Greenland">Greenland</option>
                                  <option data-id="+-472" value="Grenada">Grenada</option>
                                  <option data-id="+590" value="Guadeloupe">Guadeloupe</option>
                                  <option data-id="+-670" value="Guam">Guam</option>
                                  <option data-id="+5399" value="Guantanamo Bay">Guantanamo Bay</option>
                                  <option data-id="+502" value="Guatemala">Guatemala</option>
                                  <option data-id="+245" value="Guinea-Bissau">Guinea-Bissau</option>
                                  <option data-id="+224" value="Guinea">Guinea</option>
                                  <option data-id="+592" value="Guyana">Guyana</option>
                                  <option data-id="+509" value="Haiti">Haiti</option>
                                  <option data-id="+504" value="Honduras">Honduras</option>
                                  <option data-id="+852" value="Hong Kong">Hong Kong</option>
                                  <option data-id="+36" value="Hungary">Hungary</option>
                                  <option data-id="++8810, +88" value="ICO Global (Mobile Satellite Service)">ICO Global (Mobile Satellite Service)</option>
                                  <option data-id="+354" value="Iceland">Iceland</option>
                                  <option data-id="+91" value="India">India</option>
                                  <option data-id="+62" value="Indonesia">Indonesia</option>
                                  <option data-id="+871" value="Inmarsat (Atlantic Ocean - East)">Inmarsat (Atlantic Ocean - East)</option>
                                  <option data-id="+874" value="Inmarsat (Atlantic Ocean - West)">Inmarsat (Atlantic Ocean - West)</option>
                                  <option data-id="+873" value="Inmarsat (Indian Ocean)">Inmarsat (Indian Ocean)</option>
                                  <option data-id="+872" value="Inmarsat (Pacific Ocean)">Inmarsat (Pacific Ocean)</option>
                                  <option data-id="+870" value="Inmarsat SNAC">Inmarsat SNAC</option>
                                  <option data-id="+" value="Note: Inmarsat plans to shift all other codes to t">Note: Inmarsat plans to shift all other codes to t</option>
                                  <option data-id="+800" value="International Freephone Service">International Freephone Service</option>
                                  <option data-id="+808" value="International Shared Cost Service (ISCS)">International Shared Cost Service (ISCS)</option>
                                  <option data-id="+98" value="Iran">Iran</option>
                                  <option data-id="+964" value="Iraq">Iraq</option>
                                  <option data-id="+353" value="Ireland">Ireland</option>
                                  <option data-id="++8816, +88" value="Iridium (Mobile Satellite service)">Iridium (Mobile Satellite service)</option>
                                  <option data-id="+972" value="Israel">Israel</option>
                                  <option data-id="+39" value="Italy">Italy</option>
                                  <option data-id="+-875" value="Jamaica">Jamaica</option>
                                  <option data-id="+81" value="Japan">Japan</option>
                                  <option data-id="+962" value="Jordan">Jordan</option>
                                  <option data-id="+7" value="Kazakhstan">Kazakhstan</option>
                                  <option data-id="+254" value="Kenya">Kenya</option>
                                  <option data-id="+686" value="Kiribati">Kiribati</option>
                                  <option data-id="+850" value="Korea (North)">Korea (North)</option>
                                  <option data-id="+82" value="Korea (South)">Korea (South)</option>
                                  <option data-id="+965" value="Kuwait">Kuwait</option>
                                  <option data-id="+996" value="Kyrgyz Republic">Kyrgyz Republic</option>
                                  <option data-id="+856" value="Laos">Laos</option>
                                  <option data-id="+371" value="Latvia">Latvia</option>
                                  <option data-id="+961" value="Lebanon">Lebanon</option>
                                  <option data-id="+266" value="Lesotho">Lesotho</option>
                                  <option data-id="+231" value="Liberia">Liberia</option>
                                  <option data-id="+218" value="Libya">Libya</option>
                                  <option data-id="+423" value="Liechtenstein">Liechtenstein</option>
                                  <option data-id="+370" value="Lithuania">Lithuania</option>
                                  <option data-id="+352" value="Luxembourg">Luxembourg</option>
                                  <option data-id="+853" value="Macao">Macao</option>
                                  <option data-id="+389" value="Macedonia (Former Yugoslav Rep of.)">Macedonia (Former Yugoslav Rep of.)</option>
                                  <option data-id="+261" value="Madagascar">Madagascar</option>
                                  <option data-id="+265" value="Malawi">Malawi</option>
                                  <option data-id="+60" value="Malaysia">Malaysia</option>
                                  <option data-id="+960" value="Maldives">Maldives</option>
                                  <option data-id="+223" value="Mali Republic">Mali Republic</option>
                                  <option data-id="+356" value="Malta">Malta</option>
                                  <option data-id="+692" value="Marshall Islands">Marshall Islands</option>
                                  <option data-id="+596" value="Martinique">Martinique</option>
                                  <option data-id="+222" value="Mauritania">Mauritania</option>
                                  <option data-id="+230" value="Mauritius">Mauritius</option>
                                  <option data-id="+269" value="Mayotte Island">Mayotte Island</option>
                                  <option data-id="+52" value="Mexico">Mexico</option>
                                  <option data-id="+691" value="Micronesia, (Federal States of)">Micronesia, (Federal States of)</option>
                                  <option data-id="+-807" value="Midway Island">Midway Island</option>
                                  <option data-id="+373" value="Moldova">Moldova</option>
                                  <option data-id="+377" value="Monaco">Monaco</option>
                                  <option data-id="+976" value="Mongolia">Mongolia</option>
                                  <option data-id="+382" value="Montenegro">Montenegro</option>
                                  <option data-id="+-663" value="Montserrat">Montserrat</option>
                                  <option data-id="+212" value="Morocco">Morocco</option>
                                  <option data-id="+258" value="Mozambique">Mozambique</option>
                                  <option data-id="+95" value="Myanmar">Myanmar</option>
                                  <option data-id="+264" value="Namibia">Namibia</option>
                                  <option data-id="+674" value="Nauru">Nauru</option>
                                  <option data-id="+977" value="Nepal">Nepal</option>
                                  <option data-id="+31" value="Netherlands">Netherlands</option>
                                  <option data-id="+599" value="Netherlands Antilles">Netherlands Antilles</option>
                                  <option data-id="+-868" value="Nevis">Nevis</option>
                                  <option data-id="+687" value="New Caledonia">New Caledonia</option>
                                  <option data-id="+64" value="New Zealand">New Zealand</option>
                                  <option data-id="+505" value="Nicaragua">Nicaragua</option>
                                  <option data-id="+227" value="Niger">Niger</option>
                                  <option data-id="+234" value="Nigeria">Nigeria</option>
                                  <option data-id="+683" value="Niue">Niue</option>
                                  <option data-id="+672" value="Norfolk Island">Norfolk Island</option>
                                  <option data-id="+-669" value="Northern Marianas Islands">Northern Marianas Islands</option>
                                  <option data-id="+" value="(Saipan, Rota, &amp; Tinian)">(Saipan, Rota, &amp; Tinian)</option>
                                  <option data-id="+47" value="Norway">Norway</option>
                                  <option data-id="+968" value="Oman">Oman</option>
                                  <option data-id="+92" value="Pakistan">Pakistan</option>
                                  <option data-id="+680" value="Palau">Palau</option>
                                  <option data-id="+970" value="Palestinian Settlements">Palestinian Settlements</option>
                                  <option data-id="+507" value="Panama">Panama</option>
                                  <option data-id="+675" value="Papua New Guinea">Papua New Guinea</option>
                                  <option data-id="+595" value="Paraguay">Paraguay</option>
                                  <option data-id="+51" value="Peru">Peru</option>
                                  <option data-id="+63" value="Philippines">Philippines</option>
                                  <option data-id="+48" value="Poland">Poland</option>
                                  <option data-id="+351" value="Portugal">Portugal</option>
                                  <option data-id="++1-787 or " value="Puerto Rico">Puerto Rico</option>
                                  <option data-id="+974" value="Qatar">Qatar</option>
                                  <option data-id="+262" value="Réunion Island">Réunion Island</option>
                                  <option data-id="+40" value="Romania">Romania</option>
                                  <option data-id="+7" value="Russia">Russia</option>
                                  <option data-id="+250" value="Rwandese Republic">Rwandese Republic</option>
                                  <option data-id="+290" value="St. Helena">St. Helena</option>
                                  <option data-id="+-868" value="St. Kitts/Nevis">St. Kitts/Nevis</option>
                                  <option data-id="+-757" value="St. Lucia">St. Lucia</option>
                                  <option data-id="+508" value="St. Pierre &amp; Miquelon">St. Pierre &amp; Miquelon</option>
                                  <option data-id="+-783" value="St. Vincent &amp; Grenadines">St. Vincent &amp; Grenadines</option>
                                  <option data-id="+685" value="Samoa">Samoa</option>
                                  <option data-id="+378" value="San Marino">San Marino</option>
                                  <option data-id="+239" value="São Tomé and Principe">São Tomé and Principe</option>
                                  <option data-id="+966" value="Saudi Arabia">Saudi Arabia</option>
                                  <option data-id="+221" value="Senegal">Senegal</option>
                                  <option data-id="+381" value="Serbia">Serbia</option>
                                  <option data-id="+248" value="Seychelles Republic">Seychelles Republic</option>
                                  <option data-id="+232" value="Sierra Leone">Sierra Leone</option>
                                  <option data-id="+65" value="Singapore">Singapore</option>
                                  <option data-id="+421" value="Slovak Republic">Slovak Republic</option>
                                  <option data-id="+386" value="Slovenia">Slovenia</option>
                                  <option data-id="+677" value="Solomon Islands">Solomon Islands</option>
                                  <option data-id="+252" value="Somali Democratic Republic">Somali Democratic Republic</option>
                                  <option data-id="+27" value="South Africa">South Africa</option>
                                  <option data-id="+34" value="Spain">Spain</option>
                                  <option data-id="+94" value="Sri Lanka">Sri Lanka</option>
                                  <option data-id="+249" value="Sudan">Sudan</option>
                                  <option data-id="+597" value="Suriname">Suriname</option>
                                  <option data-id="+268" value="Swaziland">Swaziland</option>
                                  <option data-id="+46" value="Sweden">Sweden</option>
                                  <option data-id="+41" value="Switzerland">Switzerland</option>
                                  <option data-id="+963" value="Syria">Syria</option>
                                  <option data-id="+886" value="Taiwan">Taiwan</option>
                                  <option data-id="+992" value="Tajikistan">Tajikistan</option>
                                  <option data-id="+255" value="Tanzania">Tanzania</option>
                                  <option data-id="+66" value="Thailand">Thailand</option>
                                  <option data-id="+88216" value="Thuraya (Mobile Satellite service)">Thuraya (Mobile Satellite service)</option>
                                  <option data-id="+670" value="Timor Leste">Timor Leste</option>
                                  <option data-id="+228" value="Togolese Republic">Togolese Republic</option>
                                  <option data-id="+690" value="Tokelau">Tokelau</option>
                                  <option data-id="+676" value="Tonga Islands">Tonga Islands</option>
                                  <option data-id="+-867" value="Trinidad &amp; Tobago">Trinidad &amp; Tobago</option>
                                  <option data-id="+216" value="Tunisia">Tunisia</option>
                                  <option data-id="+90" value="Turkey">Turkey</option>
                                  <option data-id="+993" value="Turkmenistan">Turkmenistan</option>
                                  <option data-id="+-648" value="Turks and Caicos Islands">Turks and Caicos Islands</option>
                                  <option data-id="+688" value="Tuvalu">Tuvalu</option>
                                  <option data-id="+256" value="Uganda">Uganda</option>
                                  <option data-id="+380" value="Ukraine">Ukraine</option>
                                  <option data-id="+971" value="United Arab Emirates">United Arab Emirates</option>
                                  <option data-id="+44" value="United Kingdom">United Kingdom</option>
                                  <option data-id="+1" value="United States of America">United States of America</option>
                                  <option data-id="+-339" value="US Virgin Islands">US Virgin Islands</option>
                                  <option data-id="+878" value="Universal Personal Telecommunications (UPT)">Universal Personal Telecommunications (UPT)</option>
                                  <option data-id="+598" value="Uruguay">Uruguay</option>
                                  <option data-id="+998" value="Uzbekistan">Uzbekistan</option>
                                  <option data-id="+678" value="Vanuatu">Vanuatu</option>
                                  <option data-id="++39, +379" value="Vatican City">Vatican City</option>
                                  <option data-id="+58" value="Venezuela">Venezuela</option>
                                  <option data-id="+84" value="Vietnam">Vietnam</option>
                                  <option data-id="+808" value="Wake Island">Wake Island</option>
                                  <option data-id="+681" value="Wallis and Futuna Islands">Wallis and Futuna Islands</option>
                                  <option data-id="+967" value="Yemen">Yemen</option>
                                  <option data-id="+260" value="Zambia">Zambia</option>
                                  <option data-id="+255" value="Zanzibar">Zanzibar</option>
                                  <option data-id="+263" value="Zimbabwe">Zimbabwe</option>
                               </select>
                            </div>
                         </div>
                      </div>
                      <div class="col-xs-6 col-sm-6 col-md-6">
                         <label class="fieldlabels lebel-title">Phone <span style="color:red">*</span></label>
                         <div class="form-group">
                            <div class="input-group input-group-merge input-group-alternative">
                               <div class="input-group-prepend">
                                  <span class="input-group-text" id="setIdValue">+880</span>
                               </div>
                               <input type="number" name="phone" placeholder="Please Enter Valid Phone Number" id="caPhone" data-v-min="10" pattern="[0-9]+" title="Only number." class="form-control input-sm" required>
                            </div>
                         </div>
                      </div>
                   </div>
                   <div class="col-12">
                      <label class="fieldlabels lebel-title">Subject <span style="color:red">*</span></label>
                      <div class="form-group mb-3">
                         <div class="input-group input-group-merge input-group-alternative">
                            <input type="text" name="subject" id="subject" value="" class="form-control" autocomplete="off" placeholder="Please type your Quires Subject.." maxlength="80" size="30">
                         </div>
                         <span class="text-danger"></span>
                      </div>
                   </div>
                   <div class="col-12">
                      <div class="form-group mb-3">
                         <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Details</label>
                            <textarea class="form-control" id="exampleFormControlTextarea1" rows="3"></textarea>
                         </div>
                         <span class="text-danger"></span>
                      </div>
                   </div>
                   <div class="col-12">
                      <div class="text-center">
                         <button class="btn btn-primary bd-0 primary-btn-fill bics-btn" type="submit"><i class="icofont-check-alt"></i> <span> &nbsp;SEND</span> </button>
                         <button class="btn btn-danger bd-0 bics-danger-btn" onclick="window.location.reload()"><i class="icofont-ui-rotation"></i><span> &nbsp;RESET</span> </button>
                      </div>
                </form>
                </div>
                <!--<div class="card-footer">Footer</div>-->
             </div>
          </div>
       </div>
    </div>
 </div>
    
@endsection