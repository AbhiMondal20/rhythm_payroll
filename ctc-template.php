<?php
require_once 'includes/config.php';
$page_title = 'CTC Templates';
ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<div class="p-6">

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-700">CTC Templates</h2>

        <button onclick="showAddForm()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
            + Add CTC Template
        </button>
    </div>

    <div class="grid grid-cols-12 gap-6">

        <!-- LEFT LIST -->
        <div class="col-span-3 bg-white border rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-medium text-gray-500">
                List of CTC Templates
            </div>

            <div id="templateList">
                <div onclick="showDetail('Default')" 
                     class="px-4 py-3 cursor-pointer hover:bg-gray-50 border-b text-blue-600 font-medium">
                    Default
                </div>
            </div>
        </div>

        <!-- RIGHT CONTENT -->
        <div class="col-span-9 bg-white border rounded-lg p-6">

            <!-- ================= ADD FORM ================= -->
            <div id="addForm">

                <h3 class="text-lg font-semibold mb-4">ADD CTC TEMPLATE</h3>

                <div class="mb-4">
                    <label class="text-sm text-gray-600">Template Name</label>
                    <input type="text" class="w-full border-b outline-none py-1 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-6 mb-4">

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox"> Profession Tax (PT) State
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox"> Is Employee State Insurance (ESI) Applicable
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox"> Is Provident Fund (PF) Applicable
                    </label>

                </div>

                <div class="mb-6">
                    <label class="text-sm text-gray-600">Remarks</label>
                    <input type="text" class="w-full border-b outline-none py-1 text-sm">
                </div>

                <!-- Salary Sections -->
                <div class="space-y-4">

                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between text-sm font-medium mb-2">
                            <span>EARNINGS</span>
                            <span class="text-blue-600 cursor-pointer">+ Add Earnings</span>
                        </div>
                        <p class="text-xs text-gray-500">Include earning components</p>
                    </div>

                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between text-sm font-medium mb-2">
                            <span>DEDUCTIONS</span>
                            <span class="text-blue-600 cursor-pointer">+ Add Deductions</span>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between text-sm font-medium mb-2">
                            <span>EMPLOYER CONTRIBUTION</span>
                            <span class="text-blue-600 cursor-pointer">+ Add Employer Contribution</span>
                        </div>
                    </div>

                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="border px-4 py-2 rounded text-sm">Cancel</button>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Add</button>
                </div>

            </div>


            <!-- ================= DETAIL VIEW ================= -->
            <div id="detailView" style="display:none">

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">DEFAULT</h3>
                    <button onclick="showAddForm()" class="text-blue-600 text-sm">Edit Details</button>
                </div>

                <div class="mb-4">
                    <label class="text-sm text-gray-500">Template Name</label>
                    <p class="border-b py-1 text-sm">Default</p>
                </div>

                <div class="grid grid-cols-2 gap-6 mb-4 text-sm">

                    <div>✔ Profession Tax (PT) State — West Bengal</div>
                    <div>✔ ESI Applicable</div>
                    <div>✔ PF Applicable</div>

                </div>

                <!-- Salary Table -->
                <div class="border rounded-lg overflow-hidden mt-4">

                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="text-left p-3">Component</th>
                                <th class="p-3">% of CTC</th>
                                <th class="p-3">Value</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr class="border-t">
                                <td class="p-3">Basic</td>
                                <td class="text-center">50</td>
                                <td class="text-center">—</td>
                            </tr>
                            <tr class="border-t">
                                <td class="p-3">HRA</td>
                                <td class="text-center">30</td>
                                <td class="text-center">—</td>
                            </tr>
                            <tr class="border-t">
                                <td class="p-3">Medical</td>
                                <td class="text-center">5</td>
                                <td class="text-center">—</td>
                            </tr>
                            <tr class="border-t">
                                <td class="p-3">Leave Travel</td>
                                <td class="text-center">10</td>
                                <td class="text-center">—</td>
                            </tr>
                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
function showDetail(name){
    document.getElementById('addForm').style.display='none';
    document.getElementById('detailView').style.display='block';
}

function showAddForm(){
    document.getElementById('addForm').style.display='block';
    document.getElementById('detailView').style.display='none';
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>