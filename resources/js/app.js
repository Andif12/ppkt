import Alpine from '@alpinejs/csp'
import '@fortawesome/fontawesome-free/css/all.min.css'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import Swal from 'sweetalert2'

window.Swal = Swal
window.Quill = Quill

window.Alpine = Alpine

Alpine.data('aduanPage', () => ({
    search: '',

    selectedAduan: null,
    showRejectForm: false,
    showConfirmModal: false,
    confirmAduanId: null,

    showModal: false,
    key: '',
    decrypted: null,
    isDecrypted: false,

    executionTime: 0,

    closeModal() {
        this.showModal = false
        this.key = ''
    },


    selectAduan(id) {
        this.selectedAduan = id
        this.showRejectForm = false
        this.isDecrypted = false
        this.decrypted = null
    },

    openConfirmModal(id) {
        this.showConfirmModal = true
        this.confirmAduanId = id
    },

    submitForm() {
        document
            .getElementById('form-kirim-' + this.confirmAduanId)
            .submit()
    },

    decryptAduan(id) {
        fetch(`/admin/aduan/decrypt/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            },
            body: JSON.stringify({ key: this.key })
        })
        .then(r => r.json())
        .then(r => {
            if (r.status === 'success') {
                this.decrypted = r.data
                this.executionTime = r.execution_time
                this.isDecrypted = true
                // this.key = ''
                this.showModal = false
            } else {
                alert(r.message)
            }
        })
        .catch(() => alert('Gagal dekripsi'))
    }
}))

Alpine.data('allPage', () => ({
    search: '',
    filter: '',
    showSuccess: true,

    showDeleteModal: false,
    deleteId: null,
    deleteName: '',

    openDeleteModal(id, name) {
        this.deleteId = id
        this.deleteName = name
        this.showDeleteModal = true
    },

    confirmDelete() {
        document.getElementById(`delete-form-${this.deleteId}`).submit()
    },

    get showNoResults() {
        if (this.search.trim() === '') return false

        const rows = document.querySelectorAll('.all-row')

        return Array.from(rows).every(row =>
            !row.innerText.toLowerCase().includes(this.search.toLowerCase())
        )
    },

    
    init() {
        setTimeout(() => {
            this.showSuccess = false
        }, 3000)
},
}))

Alpine.data('beritaPage', () => ({
    quill: null,

    init() {
        this.quill = new Quill('#quill-editor', {
            theme: 'snow'
        })

        // ambil isi lama dari textarea hidden
        const oldContent = document.getElementById('deskripsi').value

        if (oldContent) {
            this.quill.root.innerHTML = oldContent
        }

        // sync ke textarea setiap berubah
        this.quill.on('text-change', () => {
            document.getElementById('deskripsi').value =
                this.quill.root.innerHTML
        })
    }
}))

Alpine.data('stepPage', () => ({
    step: 1,
    totalSteps: 5,

    nextStep() {
        if (this.step < this.totalSteps) {
            this.step++
        }
    },

    prevStep() {
        if (this.step > 1) {
            this.step--
        }
    }
}))

document.addEventListener("DOMContentLoaded", () => {
    const toast = document.getElementById("toast");

    if (toast) {
        setTimeout(() => {
            toast.style.transition = "0.5s";
            toast.style.opacity = "0";

            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const faqButton = document.getElementById('faqButton')
    const faqButtonContainer = document.getElementById('faqButtonContainer')
    const faqPanel = document.getElementById('faqPanel')
    const closeFaq = document.getElementById('closeFaq')

    if (!faqButton || !faqPanel || !closeFaq) return

    // buka FAQ
    faqButton.addEventListener('click', () => {
        faqPanel.style.width = '300px'
        faqButtonContainer.style.display = 'none'
    })

    // tutup FAQ
    closeFaq.addEventListener('click', () => {
        faqPanel.style.width = '0px'
        faqButtonContainer.style.display = 'block'
    })
})

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-back').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault()
            history.back()
        })
    })
})



Alpine.data('satgasDecryptPage', (initialId) => ({
    showModal: false,
    key: '',
    decrypted: null,
    isDecrypted: false,
    executionTime: 0,
    aduanId: initialId,

    closeModal() {
        this.showModal = false
        this.key = ''
    },

    decryptAduan() {
        fetch(`/satgas/aduan/decrypt/${this.aduanId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : ''
            },
            body: JSON.stringify({ key: this.key })
        })
        .then(r => r.json())
        .then(r => {
            if (r.status === 'success') {
                this.decrypted = r.data
                this.executionTime = r.execution_time
                this.isDecrypted = true
                // this.key = ''
                this.showModal = false
            } else {
                alert(r.message)
            }
        })
        .catch(() => alert('Gagal dekripsi'))
    }
}))

Alpine.start()
