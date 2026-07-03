import Swal from 'sweetalert2'

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
})

export function toastSuccess(message) {
  Toast.fire({ icon: 'success', title: message })
}

export function toastError(message) {
  Toast.fire({ icon: 'error', title: message, timer: 5000 })
}

export function alertErrors(title, errors) {
  Swal.fire({
    icon: 'error',
    title,
    html: `<ul style="text-align:left;font-size:0.9rem;max-height:300px;overflow-y:auto;padding-left:1.2rem">${errors.map((e) => `<li>${e}</li>`).join('')}</ul>`,
    confirmButtonColor: '#1d4ed8',
  })
}

export function alertSuccess(title, message) {
  Swal.fire({
    icon: 'success',
    title,
    text: message,
    confirmButtonColor: '#1d4ed8',
  })
}
