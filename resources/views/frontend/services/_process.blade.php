<div class="service-process-list">
    @foreach([['bi-ui-checks','સેવા અને વિગતો પસંદ કરો','Choose the service and submit accurate information.'],['bi-cloud-arrow-up','મિલકત દસ્તાવેજ અપલોડ કરો','Upload only accepted property documents securely.'],['bi-hash','સંદર્ભ નંબર મેળવો','Receive the unique reference number after submission.'],['bi-search','વિનંતી ટ્રેક કરો','Track public updates with the reference and mobile number.']] as $index => $step)
        <div class="service-process-step"><span>{{ $index + 1 }}</span><i class="bi {{ $step[0] }}" aria-hidden="true"></i><div><strong>{{ $step[1] }}</strong><small>{{ $step[2] }}</small></div></div>
    @endforeach
</div>
